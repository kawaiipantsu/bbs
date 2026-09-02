/* terminal.js - renders server "frames" onto the CRT grid and turns keystrokes
   into /api/action calls. Modes: menu, pager, screen, line, form, game, chat. */

import { sound } from './audio.js';

/* xterm-256 palette for foreground indices > 15 (games / imported ANSI) */
function xterm256(i) {
  if (i < 16) return null;
  if (i < 232) {
    i -= 16;
    const r = Math.floor(i / 36), g = Math.floor((i % 36) / 6), b = i % 6;
    const c = v => (v ? v * 40 + 55 : 0);
    return `rgb(${c(r)},${c(g)},${c(b)})`;
  }
  const v = (i - 232) * 10 + 8;
  return `rgb(${v},${v},${v})`;
}

const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/* explode a row (array of runs) into one styled cell per visible column */
function rowToCells(runs) {
  const cells = [];
  for (const run of runs || []) {
    for (const ch of [...run.s]) {
      cells.push({ ch, f: run.f, b: run.b, o: run.o, k: run.k });
    }
  }
  return cells;
}
/* coalesce cells back into runs with identical style merged */
function cellsToRuns(cells) {
  const runs = [];
  let cur = null;
  for (const c of cells) {
    if (cur && cur.f === c.f && cur.b === c.b && cur.o === c.o && cur.k === c.k) {
      cur.s += c.ch;
    } else {
      cur = { s: c.ch, f: c.f, b: c.b, o: c.o, k: c.k };
      runs.push(cur);
    }
  }
  return runs;
}

/* client-side |NN pipe parser (used by the boot sequence) */
export function clientPipe(str) {
  const runs = [];
  let fg = 7, bg = 0, buf = '';
  const flush = () => { if (buf) { runs.push({ s: buf, f: fg, b: bg, o: fg >= 8, k: false }); buf = ''; } };
  for (let i = 0; i < str.length; i++) {
    if (str[i] === '|' && /\d\d/.test(str.substr(i + 1, 2))) {
      flush();
      const n = parseInt(str.substr(i + 1, 2), 10);
      if (n <= 15) fg = n; else if (n <= 23) bg = n - 16;
      i += 2;
    } else buf += str[i];
  }
  flush();
  return runs;
}

export class Terminal {
  constructor(screenEl, opts = {}) {
    this.screen = screenEl;
    this.cols = opts.cols || 132;
    this.rows = opts.rows || 50;
    this.term = document.createElement('div');
    this.term.className = 'term';
    this.screen.appendChild(this.term);

    this.frame = null;
    this.mode = 'pager';
    this.scroll = 0;
    this.sel = 0;
    this.line = '';
    this.form = null;
    this.onSend = () => {};
    this.onChatKey = null;      // chat.js installs this
    this.busy = false;

    this._k = 0.6;            // char-width / font-size for the mono face
    this._fileInput = null;
    this._measure();
    let raf = 0;
    window.addEventListener('resize', () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => this.resize());
    });
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(() => { this._measure(); this.resize(); });
    }
  }

  setSend(fn) { this.onSend = fn; }

  /* ---- sizing ------------------------------------------------------
     Monospace: charWidth = k * fontSize. Measure k at a big size, then
     pick fontSize so `cols` cells span the glass exactly, and set an
     independent line-height so `rows` lines span the glass height.
     (Non-square cells give the stretched-CRT look for free.) */
  _measure() {
    const probe = document.createElement('span');
    probe.style.cssText =
      'position:absolute;visibility:hidden;white-space:pre;font:400 200px/1 "BBS Terminal",monospace;';
    probe.textContent = 'MMMMMMMMMM';
    document.body.appendChild(probe);
    const w = probe.getBoundingClientRect().width;
    document.body.removeChild(probe);
    if (w > 0) this._k = w / 10 / 200;
  }

  resize() {
    const glass = document.getElementById('glass').getBoundingClientRect();
    if (!glass.width) return;
    // keep the text well inside the glass so it clears the rounded corners
    const M = Math.max(36, Math.round(Math.min(glass.width, glass.height) * 0.06));
    const usableW = glass.width - M * 2;
    const usableH = glass.height - M * 2;
    const fontSize = usableW / (this.cols * this._k);
    const lineH = usableH / this.rows;
    this.term.style.transform = 'none';
    this.term.style.left = M + 'px';
    this.term.style.top = M + 'px';
    this.term.style.width = usableW + 'px';
    this.term.style.height = usableH + 'px';
    this.term.style.fontSize = fontSize.toFixed(3) + 'px';
    this.term.style.lineHeight = lineH.toFixed(3) + 'px';
    this.term.style.setProperty('--lh', lineH.toFixed(3) + 'px');
    this.render();
  }

  /* ---- rendering --------------------------------------------------- */
  renderFrame(frame) {
    if (!frame) return;
    this.frame = frame;
    this.mode = frame.mode || 'pager';
    this.scroll = 0;
    this.sel = 0;
    this.line = '';

    if (this.mode === 'form') {
      const values = {};
      (frame.fields || []).forEach(f => { values[f.name] = f.value != null ? String(f.value) : ''; });
      this.form = { fields: frame.fields || [], values, idx: 0 };
    } else {
      this.form = null;
    }

    if (frame.sound) this._playFrameSound(frame.sound);
    if (frame.mode === 'redirect') { this._redirect(frame.meta || {}); return; }
    if (frame.mode === 'chat' && this.onChatEnter) { this.onChatEnter(frame); return; }

    this.render();
  }

  _playFrameSound(name) {
    ({ beep: () => sound.beep(), bell: () => sound.bell(), error: () => sound.error(),
       connect: () => sound.connectChime(), hangup: () => sound.hangup() }[name] || (() => {}))();
  }

  _redirect(meta) {
    if (meta.download) {
      const a = document.createElement('a');
      a.href = meta.url; a.download = meta.download || '';
      document.body.appendChild(a); a.click(); a.remove();
    } else if (meta.url) {
      window.open(meta.url, meta.newtab ? '_blank' : '_self', 'noopener');
    }
    // redraw whatever screen we were on
    this.onSend({ cmd: 'noop' });
  }

  /** Build the array of display rows (each = array of runs), then paint. */
  render() {
    if (!this.frame) return;
    let rows = (this.frame.lines || []).map(r => r.slice());

    if (this.mode === 'form') rows = rows.concat(this._formRows());
    else if (this.mode === 'line') rows = rows.concat(this._lineRows());
    else if (this.mode === 'menu') rows = this._applyMenuHighlight(rows);

    const total = rows.length;
    const maxTop = Math.max(0, total - this.rows);
    if (this.scroll > maxTop) this.scroll = maxTop;
    const view = rows.slice(this.scroll, this.scroll + this.rows);

    // MORE indicator
    const more = this.scroll < maxTop;
    if (more && view.length) {
      view[view.length - 1] = [{ s: ' -- MORE -- (SPACE) ', f: 0, b: 11, o: true, k: true }];
    }
    while (view.length < this.rows) view.push([]);

    this.term.innerHTML = view.map(row => this._rowHtml(row)).join('');
    this._placeCursor();
  }

  /** Paint an explicit array of rows (each a pipe string or run array). No mode change. */
  paint(rows) {
    const view = rows.slice(-this.rows).map(r => (typeof r === 'string' ? clientPipe(r) : r));
    while (view.length < this.rows) view.push([]);
    this.term.innerHTML = view.map(row => this._rowHtml(row)).join('');
  }

  _rowHtml(runs) {
    let html = '<div class="row">';
    let col = 0;
    for (const run of runs || []) {
      const cls = ['f' + (run.f > 15 ? 7 : run.f)];
      if (run.b) cls.push('b' + run.b);
      if (run.k) cls.push('bl');
      let style = '';
      const x = xterm256(run.f);
      if (x) style += 'color:' + x + ';';
      if (run.o && run.f < 8) style += 'font-weight:700;';
      html += `<span class="${cls.join(' ')}"${style ? ` style="${style}"` : ''}>${esc(run.s)}</span>`;
      col += [...run.s].length;
    }
    html += '</div>';
    return html;
  }

  _placeCursor() {
    // cursor is inlined into the row for line/form modes via _lineRows/_formRows
  }

  /* ---- LINE mode -------------------------------------------------- */
  _lineRows() {
    const prompt = (this.frame.prompt || '') + ': ';
    return [
      [],
      [
        { s: '  ' + prompt, f: 10, b: 0, o: true, k: false },
        { s: this.line, f: 15, b: 0, o: false, k: false },
        { s: ' ', f: 0, b: 15, o: false, k: true },
      ],
    ];
  }

  /* ---- MENU highlight ---------------------------------------------------
     Highlights the currently selected item IN PLACE: a red bar over its
     [key] Label cell plus a ▸ marker in the margin, using the row/col the
     server recorded in meta.items. Also keeps the selection on screen and
     appends the item's description under the list. */
  _applyMenuHighlight(rows) {
    const items = (this.frame.meta && this.frame.meta.items) || [];
    if (!items.length) return rows;
    if (this.sel < 0) this.sel = items.length - 1;
    if (this.sel >= items.length) this.sel = 0;

    const it = items[this.sel];
    if (it && typeof it.row === 'number' && rows[it.row]) {
      const cells = rowToCells(rows[it.row]);
      const from = Math.max(0, it.col | 0);
      const to = from + (it.w | 0 || 24);
      while (cells.length < to) cells.push({ ch: ' ', f: 7, b: 0, o: false, k: false });
      for (let i = from; i < to; i++) {
        cells[i].b = 9;                                  // accent-dim bar
        cells[i].f = cells[i].f === 8 || cells[i].f === 0 ? 15 : cells[i].f;
        cells[i].o = true;
      }
      const m = Math.max(0, from - 2);
      cells[m] = { ch: '▸', f: 9, b: 0, o: true, k: false };
      rows[it.row] = cellsToRuns(cells);

      // keep it visible
      if (it.row < this.scroll) this.scroll = it.row;
      else if (it.row >= this.scroll + this.rows - 2) this.scroll = it.row - this.rows + 3;
      if (this.scroll < 0) this.scroll = 0;
    }

    if (it && it.description) {
      rows = rows.concat([[], [
        { s: '   ', f: 7, b: 0, o: false, k: false },
        { s: it.label + ': ', f: 14, b: 0, o: true, k: false },
        { s: it.description, f: 7, b: 0, o: false, k: false },
      ]]);
    }
    return rows;
  }

  /* ---- FORM mode -------------------------------------------------- */
  _formRows() {
    const rows = [[]];
    const f = this.form;
    f.fields.forEach((fld, i) => {
      const active = i === f.idx;
      let val = f.values[fld.name] || '';
      let shown;
      if (fld.type === 'password') shown = '*'.repeat(val.length);
      else if (fld.type === 'select') {
        const opts = fld.options || {};
        shown = opts[val] || Object.values(opts)[0] || '(choose)';
        shown = '◄ ' + shown + ' ►';
      } else if (fld.type === 'file') {
        shown = val ? (JSON.parse(val).name || 'file selected') : '(press ENTER to choose a file)';
      } else if (fld.type === 'textarea') {
        shown = val.split('\n')[0] + (val.includes('\n') ? ' ↵…' : '');
      } else shown = val;

      const label = ('  ' + fld.label).padEnd(34, ' ').slice(0, 34);
      const cur = active ? '█' : '';
      rows.push([
        { s: label + ': ', f: active ? 15 : 7, b: 0, o: active, k: false },
        { s: shown + cur, f: 15, b: active ? 1 : 0, o: false, k: active },
      ]);
      if (fld.type === 'textarea' && active && val.includes('\n')) {
        val.split('\n').slice(1).forEach(ln =>
          rows.push([{ s: ' '.repeat(37) + ln, f: 15, b: 1, o: false, k: false }]));
      }
    });
    const onSubmit = f.idx === f.fields.length;
    const onCancel = f.idx === f.fields.length + 1;
    rows.push([]);
    rows.push([
      { s: '   ', f: 7, b: 0, o: false, k: false },
      { s: '[ SUBMIT ]', f: onSubmit ? 0 : 10, b: onSubmit ? 10 : 0, o: true, k: false },
      { s: '   ', f: 7, b: 0, o: false, k: false },
      { s: '[ CANCEL ]', f: onCancel ? 0 : 9, b: onCancel ? 9 : 0, o: true, k: false },
      { s: '     TAB/↑↓ move · ENTER next/submit · ESC cancel', f: 8, b: 0, o: false, k: false },
    ]);
    return rows;
  }

  /* ---- keyboard --------------------------------------------------- */
  key(ev) {
    if (this.busy) return;
    const k = this._norm(ev);
    if (k === null) return;

    if (this.mode === 'chat' && this.onChatKey) { this.onChatKey(k, ev); return; }

    switch (this.mode) {
      case 'form':   return this._formKey(k, ev);
      case 'line':   return this._lineKey(k, ev);
      case 'pager':  return this._pagerKey(k);
      case 'menu':   return this._menuKey(k);
      case 'game':   return this._gameKey(k);
      default:       return this._pagerKey(k);
    }
  }

  _norm(ev) {
    const map = {
      ArrowUp: 'UP', ArrowDown: 'DOWN', ArrowLeft: 'LEFT', ArrowRight: 'RIGHT',
      Enter: 'ENTER', Escape: 'ESC', Backspace: 'BACKSPACE', Tab: 'TAB',
      ' ': 'SPACE', PageUp: 'PAGEUP', PageDown: 'PAGEDOWN', Home: 'HOME', End: 'END',
      Delete: 'DELETE',
    };
    if (map[ev.key]) return map[ev.key];
    if (ev.key && ev.key.length === 1) return ev.key;
    return null;
  }

  _send(payload) {
    this.busy = true;
    // hard safety net: never let `busy` stay stuck if the promise hangs
    // (e.g. a fetch left in flight while the tab was backgrounded).
    clearTimeout(this._busyGuard);
    this._busyGuard = setTimeout(() => { this.busy = false; }, 8000);
    const clear = () => { clearTimeout(this._busyGuard); this.busy = false; };
    Promise.resolve()
      .then(() => this.onSend(payload))
      .then(clear, clear);
  }

  /** Force-unstick input (called when the tab regains focus/visibility). */
  unstick() {
    clearTimeout(this._busyGuard);
    this.busy = false;
  }

  _scrollBy(n) {
    const total = ((this.frame.lines || []).length) + (this.mode === 'menu' ? 3 : 0);
    const maxTop = Math.max(0, total - this.rows);
    this.scroll = Math.min(maxTop, Math.max(0, this.scroll + n));
    this.render();
  }

  _atBottom() {
    const total = (this.frame.lines || []).length;
    return this.scroll >= Math.max(0, total - this.rows);
  }

  _pagerKey(k) {
    if (k === 'SPACE' || k === 'PAGEDOWN') {
      if (!this._atBottom()) return this._scrollBy(this.rows - 3);
      return this._send({ key: 'ENTER' });
    }
    if (k === 'DOWN') { if (!this._atBottom()) return this._scrollBy(2); return this._send({ key: 'ENTER' }); }
    if (k === 'B' || k === 'b' || k === 'PAGEUP') return this._scrollBy(-(this.rows - 3));
    if (k === 'UP') return this._scrollBy(-2);
    if (k === 'ENTER') return this._send({ key: 'ENTER' });
    if (k === 'ESC' || k === 'Q' || k === 'q') return this._send({ key: 'Q' });
    // any other key while boot MOTD -> advance
    if (this.frame.meta && this.frame.meta.boot) return this._send({ key: 'ENTER' });
    return this._send({ key: k.length === 1 ? k.toUpperCase() : k });
  }

  _menuKey(k) {
    const items = (this.frame.meta && this.frame.meta.items) || [];
    if (items.length && (k === 'UP' || k === 'DOWN')) {
      this.sel = (this.sel + (k === 'DOWN' ? 1 : items.length - 1)) % items.length;
      sound.move();
      return this.render();
    }
    if (!items.length && (k === 'UP' || k === 'DOWN' || k === 'PAGEUP' || k === 'PAGEDOWN')) {
      return this._scrollBy(k.startsWith('PAGE') ? (k === 'PAGEDOWN' ? this.rows - 3 : -(this.rows - 3)) : (k === 'DOWN' ? 2 : -2));
    }
    if (k === 'ENTER') {
      if (items.length) return this._send({ key: items[this.sel].hotkey });
      return this._send({ key: 'ENTER' });
    }
    if (k === 'ESC') return this._send({ key: 'ESC' });
    if (k === 'SPACE') return this._send({ key: ' ' });
    if (k === 'BACKSPACE') return this._send({ key: 'ESC' });
    if (k.length === 1) return this._send({ key: k.toUpperCase() });
  }

  _gameKey(k) {
    if (k === 'ESC') return this._send({ key: 'ESC' });
    if (k === 'ENTER') return this._send({ key: 'ENTER' });
    if (k === 'SPACE') return this._send({ key: ' ' });
    if (['UP', 'DOWN', 'LEFT', 'RIGHT'].includes(k)) return this._send({ key: k });
    if (k === 'BACKSPACE') return this._send({ key: 'BACKSPACE' });
    if (k.length === 1) return this._send({ key: k.toUpperCase() });
  }

  _lineKey(k, ev) {
    if (k === 'ENTER') { const v = this.line; this.line = ''; return this._send({ input: v }); }
    if (k === 'ESC') { this.line = ''; return this._send({ cmd: 'cancel' }); }
    if (k === 'BACKSPACE') { this.line = this.line.slice(0, -1); sound.key(); return this.render(); }
    if (k === 'SPACE') { this.line += ' '; sound.key(); return this.render(); }
    if (k.length === 1 && !ev.ctrlKey && !ev.metaKey) { this.line += k; sound.key(); return this.render(); }
  }

  _formKey(k, ev) {
    const f = this.form;
    const n = f.fields.length;
    const fld = f.fields[f.idx];

    if (k === 'ESC') return this._send({ cmd: 'cancel' });
    if (ev.ctrlKey && k === 'ENTER') return this._submitForm();

    if (k === 'TAB') { f.idx = (f.idx + (ev.shiftKey ? n + 1 : 1)) % (n + 2); sound.move(); return this.render(); }
    if (k === 'UP') { f.idx = (f.idx + n + 1) % (n + 2); sound.move(); return this.render(); }
    if (k === 'DOWN') { f.idx = (f.idx + 1) % (n + 2); sound.move(); return this.render(); }

    // buttons
    if (f.idx === n) { if (k === 'ENTER' || k === 'SPACE') return this._submitForm(); return; }
    if (f.idx === n + 1) { if (k === 'ENTER' || k === 'SPACE') return this._send({ cmd: 'cancel' }); return; }

    if (!fld) return;

    if (fld.type === 'select') {
      const keys = Object.keys(fld.options || {});
      let i = keys.indexOf(String(f.values[fld.name]));
      if (i < 0) i = 0;
      if (k === 'LEFT') i = (i + keys.length - 1) % keys.length;
      if (k === 'RIGHT' || k === 'SPACE') i = (i + 1) % keys.length;
      f.values[fld.name] = keys[i];
      if (k === 'ENTER') { f.idx++; }
      return this.render();
    }

    if (fld.type === 'file') {
      if (k === 'ENTER' || k === 'SPACE') return this._pickFile(fld);
      return;
    }

    if (k === 'ENTER') {
      if (fld.type === 'textarea') { f.values[fld.name] = (f.values[fld.name] || '') + '\n'; return this.render(); }
      f.idx = Math.min(n, f.idx + 1);
      return this.render();
    }
    if (k === 'BACKSPACE') { f.values[fld.name] = (f.values[fld.name] || '').slice(0, -1); sound.key(); return this.render(); }
    if (k === 'SPACE') { f.values[fld.name] = (f.values[fld.name] || '') + ' '; sound.key(); return this.render(); }
    if (k.length === 1 && !ev.ctrlKey && !ev.metaKey) {
      const max = fld.max || 4000;
      if ((f.values[fld.name] || '').length < max) f.values[fld.name] = (f.values[fld.name] || '') + k;
      sound.key();
      return this.render();
    }
  }

  _submitForm() {
    sound.beep();
    this._send({ cmd: 'submit', data: this.form.values });
  }

  _pickFile(fld) {
    if (!this._fileInput) {
      this._fileInput = document.createElement('input');
      this._fileInput.type = 'file';
      this._fileInput.style.display = 'none';
      document.body.appendChild(this._fileInput);
    }
    const inp = this._fileInput;
    inp.value = '';
    inp.onchange = () => {
      const file = inp.files[0];
      if (!file) return;
      const rd = new FileReader();
      rd.onload = () => {
        const b64 = String(rd.result).split(',')[1] || '';
        this.form.values[fld.name] = JSON.stringify({ name: file.name, b64 });
        this.render();
      };
      rd.readAsDataURL(file);
    };
    inp.click();
  }
}
