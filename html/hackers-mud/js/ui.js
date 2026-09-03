/* ui.js - DOM chrome: top bar, side panels, log, modals. Renders from state. */
import { iconCanvas, actorCanvas } from './sprites.js';
import { audio } from './audio.js';
import { openWorldMap } from './worldmap.js';

const PIPE = ['#0c0c0c', '#a01f2d', '#2f8f4f', '#b98a1e', '#2b5fa8', '#7a3f9a', '#3aa6a6', '#b8bcc8',
  '#5b6086', '#ff2d55', '#3ce88b', '#ffcf4a', '#66aaff', '#b98cff', '#66e0ff', '#f2f4ff'];

export function pipeHtml(s) {
  s = String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
  let out = '', open = false, col = null;
  s = s.replace(/\|(\d\d)/g, (_, n) => {
    n = +n;
    let c = n < 16 ? PIPE[n] : PIPE[7];
    return `\x00${c}\x00`;
  });
  const parts = s.split('\x00');
  for (let i = 0; i < parts.length; i++) {
    if (i % 2 === 1) { if (open) out += '</span>'; out += `<span style="color:${parts[i]}">`; open = true; }
    else out += parts[i];
  }
  if (open) out += '</span>';
  return out;
}

export class UI {
  constructor() { this.listeners = {}; this.last = {}; }
  on(evt, fn) { (this.listeners[evt] || (this.listeners[evt] = [])).push(fn); }
  emit(evt, d) { (this.listeners[evt] || []).forEach(f => f(d)); }

  mount(root) {
    root.innerHTML = `
    <div id="game">
      <div class="pane" id="top">
        <div class="who"><span id="uName">-</span> <span class="lv">L<span id="uLv">1</span></span>
          <span class="arch" id="uArch"></span></div>
        <div class="bars">
          <div class="bar hp"><div class="lab"><span>HP</span><span id="hpN"></span></div><div class="track"><div class="fill" id="hpF"></div></div></div>
          <div class="bar en"><div class="lab"><span>HEAT</span><span id="enN"></span></div><div class="track"><div class="fill" id="enF"></div></div></div>
          <div class="bar xp"><div class="lab"><span>XP</span><span id="xpN"></span></div><div class="track"><div class="fill" id="xpF"></div></div></div>
        </div>
        <div class="eddies">¥<span id="uEd">0</span></div>
        <div class="wanted" id="uWant" title="NCPD heat"><i class="a"></i><i class="a"></i><i></i></div>
        <div class="icons">
          <button class="btn" data-modal="inv" title="Inventory (I)">\u{1F392}</button>
          <button class="btn" data-modal="gear" title="Wear / Gear (G)">\u{1F9E5}</button>
          <button class="btn" data-modal="sheet" title="Character (C)">\u{1F464}</button>
          <button class="btn" data-modal="map" title="Map (M)">\u{1F5FA}️</button>
          <button class="btn" data-modal="social" title="Messages (P)">\u{1F4F1}<span class="badge" id="unread" hidden>0</span></button>
          <button class="btn" data-modal="help" title="Help">?</button>
          <button class="btn" id="btnSnd" title="Sound &amp; music">\u{1F50A}</button>
          <button class="btn" id="btnQuit" title="Save &amp; exit">⏻</button>
        </div>
      </div>

      <div class="pane" id="left">
        <h3>Local Map</h3>
        <div class="body" id="mapWrap"><canvas id="mmc" width="220" height="200"></canvas>
          <div class="qbox" id="qbox"></div>
        </div>
      </div>

      <div class="pane" id="stage">
        <div id="roomName"></div>
        <div id="hint"></div>
        <div id="roomDesc"></div>
        <div id="toast"></div>
      </div>

      <div class="pane" id="right">
        <h3>Here &amp; Now</h3>
        <div class="body" id="here"></div>
      </div>

      <div class="pane" id="log">
        <div id="logBody"></div>
        <div id="cmdRow">
          <input id="cmdIn" placeholder="type a command  (help)  -  or use arrow keys to move" autocomplete="off" spellcheck="false">
          <button class="btn" id="cmdGo">Send</button>
        </div>
      </div>
      <div class="scan"></div>
      <div id="modalRoot"></div>
    </div>`;

    this.el = id => root.querySelector('#' + id);
    this.stageHost = this.el('stage');
    this.logBody = this.el('logBody');

    this.el('cmdGo').onclick = () => this._send();
    this.el('cmdIn').addEventListener('keydown', e => {
      if (e.key === 'Enter') this._send();
      else if (e.key === 'ArrowUp' && this._hist?.length) { this._hi = Math.max(0, (this._hi ?? this._hist.length) - 1); e.target.value = this._hist[this._hi] || ''; }
      else if (e.key === 'ArrowDown' && this._hist?.length) { this._hi = Math.min(this._hist.length, (this._hi ?? 0) + 1); e.target.value = this._hist[this._hi] || ''; }
      e.stopPropagation();
    });
    root.querySelectorAll('[data-modal]').forEach(b => b.onclick = () => this.emit('modal', b.dataset.modal));
    this.el('btnSnd').onclick = () => this._toggleMixer();
    this.el('btnQuit').onclick = () => this.emit('quit');

    addEventListener('keydown', e => {
      if (/input|textarea/i.test(e.target.tagName)) return;
      if (e.key === 'i' || e.key === 'I') this.emit('modal', 'inv');
      else if (e.key === 'm' || e.key === 'M') this.emit('modal', 'map');
      else if (e.key === 'c' || e.key === 'C') this.emit('modal', 'sheet');
      else if (e.key === 'g' || e.key === 'G') this.emit('modal', 'gear');
      else if (e.key === 'l' || e.key === 'L') this.emit('modal', 'loot');
      else if (e.key === 'p' || e.key === 'P') this.emit('modal', 'social');
      else if (e.key === 'Enter') { e.preventDefault(); this.el('cmdIn').focus(); }
      else if (e.key === 'Escape') { this.closeModal(); this._closeMixer(); }
    });
    return this.stageHost;
  }

  _send() {
    const v = this.el('cmdIn').value.trim();
    if (!v) return;
    (this._hist = this._hist || []).push(v); if (this._hist.length > 40) this._hist.shift();
    this._hi = this._hist.length;
    this.el('cmdIn').value = '';
    this.emit('cmd', v);
  }

  /* ---- render ---- */
  render(state) {
    const p = state.player, r = state.room;
    this.el('uName').textContent = p.name;
    this.el('uLv').textContent = p.level;
    this.el('uArch').textContent = (p.title || p.archetype) + (p.pos !== 'standing' ? '  ·  ' + p.pos : '');
    this._bar('hp', p.hp, p.maxHp); this._bar('en', p.energy, p.maxEnergy);
    this.el('xpF').style.width = Math.round(p.xpPct * 100) + '%';
    this.el('xpN').textContent = Math.round(p.xpPct * 100) + '%';
    this.el('uEd').textContent = p.money.toLocaleString();
    const w = this.el('uWant');
    w.className = 'wanted' + (p.wanted >= 60 ? ' w2' : p.wanted >= 20 ? ' w1' : '');
    w.title = 'NCPD heat: ' + p.wanted + '/100';

    this.el('roomName').innerHTML = `${this._esc(r.name)}<small>${this._esc((r.zone || '').toUpperCase())}${r.safe ? '  ·  SAFE' : ''}</small>`;
    this.el('roomDesc').innerHTML = pipeHtml(r.desc.split('\n')[0]);
    this.el('hint').innerHTML = this._hint(r);

    this._here(state);
    this._minimap(state.map);
    this._quests(state);

    const ub = this.el('unread');
    if (ub) { ub.textContent = state.unread > 9 ? '9+' : (state.unread || 0); ub.hidden = !state.unread; }

    // track hp for damage flashes elsewhere
    this.last = { hp: p.hp, level: p.level };
  }
  _bar(k, v, mx) { this.el(k + 'F').style.width = Math.max(0, Math.min(100, 100 * v / Math.max(1, mx))) + '%'; this.el(k + 'N').textContent = v + '/' + mx; }
  _esc(s) { return String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }

  _hint(r) {
    const bits = [];
    if (r.exits.some(e => e.locked && e.hackable)) bits.push('locked exit — <kbd>hack</kbd>');
    if (r.board) bits.push('<kbd>board</kbd> for bounties');
    if (r.bank) bits.push('<kbd>deposit</kbd>/<kbd>withdraw</kbd>');
    if (r.shop) bits.push('shop — walk into the keeper');
    bits.push('<kbd>WASD</kbd>/arrows move · <kbd>space</kbd> interact');
    return bits.join('<br>');
  }

  _here(state) {
    const box = this.el('here'); box.innerHTML = '';
    const r = state.room;
    const add = (spr, name, sub, actLabel, cmd, cls = '', hp) => {
      const d = document.createElement('div'); d.className = 'ent ' + cls;
      const c = document.createElement('canvas'); c.className = 'spr'; c.width = c.height = 30;
      d.appendChild(c);
      const nm = document.createElement('div'); nm.className = 'nm';
      nm.innerHTML = `<b>${this._esc(name)}</b><span>${this._esc(sub)}</span>${hp != null ? `<div class="hpm"><i style="width:${Math.round(hp * 100)}%"></i></div>` : ''}`;
      d.appendChild(nm);
      if (actLabel) { const a = document.createElement('span'); a.className = 'act'; a.textContent = actLabel; d.appendChild(a); }
      d.onclick = () => this.emit('act', cmd);
      box.appendChild(d);
      return c;
    };
    if (r.mobs.length) box.insertAdjacentHTML('beforeend', '<div class="sect">People &amp; things</div>');
    for (const m of r.mobs) {
      const act = m.hostile ? 'attack' : m.shop ? 'shop' : m.trainer ? 'train' : m.ripperdoc ? 'ripperdoc' : (m.questgiver || m.talk) ? 'talk' : null;
      const cmd = m.hostile ? 'kill ' + m.kw : m.shop ? '@shop:' + m.kw : m.trainer ? '@train:' + m.kw : m.ripperdoc ? '@ripper:' + m.kw : 'talk ' + m.kw;
      const c = add(m.sprite, m.name, `lv ${m.level} · ${m.faction}`, act, cmd,
        (m.hostile ? 'hostile ' : '') + (m.boss ? 'boss' : ''), (m.hostile || m.boss) ? m.hpPct : null);
      c.getContext('2d').drawImage(actorCanvas(m.sprite, 30), 0, 0);
      // inspect eye on the same card
      const eye = document.createElement('span');
      eye.className = 'act'; eye.textContent = '\u{1F441}️'; eye.title = 'inspect';
      eye.style.cssText = 'margin-left:4px';
      eye.onclick = ev => { ev.stopPropagation(); this.emit('inspect', m); };
      c.parentElement.appendChild(eye);
    }
    for (const op of r.players) {
      const c = add('civ', op.name, `lv ${op.level} · ${op.archetype}` + (op.title ? ' · ' + op.title : ''), 'look', 'look ' + op.name.toLowerCase());
      c.getContext('2d').drawImage(actorCanvas('civ', 30), 0, 0);
    }
    if (r.items.length) {
      const sec = document.createElement('div');
      sec.className = 'sect';
      sec.style.cssText = 'display:flex;align-items:center;justify-content:space-between';
      sec.innerHTML = `<span>On the ground</span>`;
      const lb = document.createElement('button');
      lb.className = 'btn sm'; lb.textContent = `LOOT (${r.items.length})`;
      lb.onclick = () => this.emit('modal', 'loot');
      sec.appendChild(lb);
      box.appendChild(sec);
    }
    for (const it of r.items) {
      const c = add(it.icon, it.name, 'item', 'grab', 'get ' + it.kw);
      c.getContext('2d').drawImage(iconCanvas(it.icon, 30, it.name || ''), 0, 0);
    }
    if (r.extras.length) {
      box.insertAdjacentHTML('beforeend', '<div class="sect">Look at…</div>');
      for (const kw of r.extras) {
        const d = document.createElement('div'); d.className = 'ent';
        d.innerHTML = `<div class="spr" style="display:grid;place-items:center;font-size:16px">\u{1F441}️</div><div class="nm"><b>${this._esc(kw)}</b><span>read it</span></div>`;
        d.onclick = () => this.emit('act', 'look ' + kw);
        box.appendChild(d);
      }
    }
    const nav = document.createElement('div'); nav.className = 'sect'; nav.textContent = 'Exits';
    box.appendChild(nav);
    const row = document.createElement('div'); row.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin:2px 6px';
    for (const e of r.exits) {
      if (e.hidden) continue;
      const b = document.createElement('button'); b.className = 'btn sm';
      b.textContent = (e.dir.toUpperCase()) + (e.locked ? ' \u{1F512}' : '') + '  ' + e.name;
      b.onclick = () => this.emit('act', e.locked && e.hackable ? 'hack ' + (e.keyword || 'door') : e.dir);
      row.appendChild(b);
    }
    box.appendChild(row);
  }

  _minimap(map) {
    const c = this.el('mmc'), ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
    ctx.fillStyle = '#05060c'; ctx.fillRect(0, 0, c.width, c.height);
    if (!map) return;
    const cell = 18, ox = c.width / 2, oy = c.height / 2;
    for (const k of map.cells) {
      const gx = ox + (k.x - map.cx) * cell, gy = oy - (k.y - map.cy) * cell;
      ctx.fillStyle = k.here ? '#ff2d55' : k.visited ? '#1f2740' : '#0f1220';
      ctx.fillRect(gx - 6, gy - 6, 12, 12);
      ctx.strokeStyle = k.here ? '#ff2d55' : '#2b3352'; ctx.strokeRect(gx - 6, gy - 6, 12, 12);
      ctx.strokeStyle = '#2b3352';
      for (const d of k.exits) {
        ctx.beginPath();
        if (d === 'n') { ctx.moveTo(gx, gy - 6); ctx.lineTo(gx, gy - 9); }
        else if (d === 's') { ctx.moveTo(gx, gy + 6); ctx.lineTo(gx, gy + 9); }
        else if (d === 'e') { ctx.moveTo(gx + 6, gy); ctx.lineTo(gx + 9, gy); }
        else if (d === 'w') { ctx.moveTo(gx - 6, gy); ctx.lineTo(gx - 9, gy); }
        else continue;
        ctx.stroke();
      }
      if (k.here) { ctx.fillStyle = '#fff'; ctx.fillRect(gx - 2, gy - 2, 4, 4); }
    }
    ctx.fillStyle = '#5b6086'; ctx.font = '9px ui-monospace'; ctx.fillText('level ' + map.z, 6, 12);
  }

  _quests(state) {
    const q = this.el('qbox'); q.innerHTML = '';
    if (state.bounty) {
      const b = state.bounty;
      q.insertAdjacentHTML('beforeend', `<div class="q"><b>\u{1F3AF} ${this._esc(b.name)}${b.done ? ' — READY' : ''}</b>
        <p>bounty · reward ¥${b.reward}</p><div class="pr"><i style="width:${Math.round(100 * b.have / b.need)}%;background:#ffcf4a"></i></div></div>`);
    }
    for (const j of state.quests) {
      q.insertAdjacentHTML('beforeend', `<div class="q"><b>${this._esc(j.name)}</b><p>${this._esc(j.summary)}</p>
        <div class="pr"><i style="width:${Math.round(100 * j.progress / Math.max(1, j.need))}%"></i></div>
        <span style="font-size:10px;color:var(--dim)">${j.progress}/${j.need}</span></div>`);
    }
    if (!state.bounty && !state.quests.length) q.innerHTML = '<div style="font-size:11px;color:var(--dim)">No active jobs. Find a fixer, or a job board.</div>';
  }

  log(lines) {
    if (!lines || !lines.length) return;
    for (const l of lines) {
      const d = document.createElement('div');
      d.innerHTML = pipeHtml(l === '' ? ' ' : l);
      this.logBody.appendChild(d);
    }
    while (this.logBody.children.length > 300) this.logBody.removeChild(this.logBody.firstChild);
    this.logBody.scrollTop = this.logBody.scrollHeight;
  }

  toast(text) {
    const t = this.el('toast'); t.textContent = text; t.classList.add('show');
    clearTimeout(this._tt); this._tt = setTimeout(() => t.classList.remove('show'), 1600);
  }

  /* ---- modals ---- */
  closeModal() {
    if (this._wmCleanup) { try { this._wmCleanup(); } catch (e) {} this._wmCleanup = null; }
    this.el('modalRoot').innerHTML = '';
  }
  _modal(title, bodyHtml, cls = '') {
    this.el('modalRoot').innerHTML = `<div class="modal"><div class="box ${cls}">
      <h3>${title}<button class="x">&times;</button></h3><div class="body">${bodyHtml}</div></div></div>`;
    const root = this.el('modalRoot');
    root.querySelector('.x').onclick = () => this.closeModal();
    root.querySelector('.modal').onclick = e => { if (e.target.classList.contains('modal')) this.closeModal(); };
    return root;
  }

  /* crafting-style slot grid: equipment sockets + a fixed backpack grid,
     click a slot to select, act from the detail panel */
  inventory(state) {
    const p = state.player;
    const inv = state.inventory || [];
    const eq = state.equipment || {};
    const WEAR = ['head', 'eyes', 'face', 'neck', 'torso', 'back', 'arms', 'hands', 'waist', 'legs', 'feet'];
    const HOLD = ['wield', 'held'];
    const IMPL = ['implant_neural', 'implant_ocular', 'implant_arm', 'implant_skeleton', 'implant_dermal'];
    const SLOTICON = {
      head: 'helmet', eyes: 'goggles', face: 'shades', neck: 'chain', torso: 'jacket', back: 'bag',
      arms: 'harness', hands: 'gloves', waist: 'harness', legs: 'pants', feet: 'boots',
      wield: 'pistol', held: 'phone',
      implant_neural: 'chip', implant_ocular: 'optic', implant_arm: 'servo', implant_skeleton: 'servo', implant_dermal: 'weave',
    };
    const PRIMARY = {
      weapon: ['wield', 'Wield'], armor: ['wear', 'Wear'], implant: ['implant', 'Install'],
      computer: ['hold', 'Jack in'], light: ['hold', 'Hold'], food: ['eat', 'Eat'],
      drink: ['drink', 'Drink'], drug: ['inject', 'Inject'], gadget: ['use', 'Use'], container: ['use', 'Use'],
    };

    // registry of every selectable cell
    const reg = {};
    const eqCell = s => {
      const it = eq[s];
      const key = 'e:' + s;
      if (it) reg[key] = { ...it, _eq: s };
      const lbl = s.replace('implant_', '');
      return `<button class="ivslot sock${it ? ' has' : ''}" data-key="${it ? key : ''}" title="${lbl}">
        ${it ? `<canvas width="40" height="40" data-icon="${it.icon}" data-seed="${this._esc(it.name)}"></canvas>`
             : `<canvas class="ghost" width="40" height="40" data-icon="${SLOTICON[s] || 'scrap'}" data-seed=""></canvas>`}
        <span class="sl">${lbl}</span></button>`;
    };

    const CELLS = 42; // 6-wide fixed backpack; grows in rows of 6 if over-stuffed
    const total = Math.max(CELLS, Math.ceil(inv.length / 6) * 6);
    let bag = '';
    for (let i = 0; i < total; i++) {
      const it = inv[i];
      if (it) {
        const key = 'i:' + it.id;
        reg[key] = { ...it };
        bag += `<button class="ivslot has" data-key="${key}">
          <canvas width="44" height="44" data-icon="${it.icon}" data-seed="${this._esc(it.name)}"></canvas>
          ${it.qty > 1 ? `<span class="q">${it.qty}</span>` : ''}
          ${it.illegal ? '<span class="ill">!</span>' : ''}</button>`;
      } else {
        bag += '<button class="ivslot" data-key=""></button>';
      }
    }

    const pct = Math.min(100, Math.round(((p.carry || 0) / (p.maxCarry || 1)) * 100)) || 0;
    const body = `<div class="invx">
      <div class="ivleft">
        <div class="sect">Worn</div>
        <div class="ivsocks">${WEAR.map(eqCell).join('')}</div>
        <div class="sect">Held</div>
        <div class="ivsocks hold">${HOLD.map(eqCell).join('')}</div>
        <div class="sect">Cyberware</div>
        <div class="ivsocks">${IMPL.map(eqCell).join('')}</div>
        <div class="sect ivbagh">Backpack <span class="ivwt">${p.carry} / ${p.maxCarry} kg</span></div>
        <div class="ivwbar"><i style="width:${pct}%"></i></div>
        <div class="ivbag">${bag}</div>
      </div>
      <div class="ivdet" id="ivdet"><p class="ivhint">Pick a slot to inspect it.</p></div>
    </div>`;

    const root = this._modal('Inventory', body, 'wide');
    const paint = host => host.querySelectorAll('canvas[data-icon]').forEach(c =>
      c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, c.width, c.dataset.seed || ''), 0, 0));
    paint(root);

    const det = root.querySelector('#ivdet');
    const select = key => {
      root.querySelectorAll('.ivslot').forEach(s => s.classList.toggle('sel', s.dataset.key === key && key));
      const r = reg[key];
      if (!r) { det.innerHTML = '<p class="ivhint">Empty slot.</p>'; return; }
      const meta = [r.type, r.slot ? '&lt;' + this._esc(r.slot.replace('implant_', '')) + '&gt;' : '',
        '¥' + (r.value || 0).toLocaleString(), (r.weight || 0) + 'kg'].filter(Boolean).join('  ·  ');
      const stats = [];
      if (r.dmg) stats.push('⚔ ' + r.dmg);
      if (r.armor) stats.push('\u{1F6E1} ' + r.armor);
      if (r.mods) stats.push(Object.entries(r.mods).map(([k, v]) => `${k} ${v > 0 ? '+' : ''}${v}`).join('  '));
      if (r.eff && r.eff.length) stats.push(r.eff.join(', '));
      if (r.lvl > 1) stats.push('req lv ' + r.lvl);
      const acts = [];
      if (r._eq) {
        if (r._eq.startsWith('implant_')) acts.push(['uninstall ' + r.kw, 'Uninstall', '', 'at a ripperdoc']);
        else acts.push(['remove ' + r.kw, 'Take off', 'pri']);
      } else {
        const pr = PRIMARY[r.type] || ['use', 'Use'];
        acts.push([pr[0] + ' ' + r.kw, pr[1], 'pri']);
        acts.push(['drop ' + r.kw, 'Drop', '']);
      }
      acts.push(['examine ' + r.kw, 'Examine', '']);
      det.innerHTML = `
        <div class="ivdh">
          <canvas class="ivbig" width="96" height="96" data-icon="${r.icon}" data-seed="${this._esc(r.name)}"></canvas>
          <div class="ivdi">
            <b>${this._esc(r.name)}</b>
            <div class="ivmeta">${meta}</div>
            <div class="ivchips">
              ${r._eq ? `<span class="chip" style="color:var(--grn);border-color:var(--grn)">equipped · ${this._esc(r._eq.replace('implant_', ''))}</span>` : ''}
              ${r.qty > 1 ? `<span class="chip">x${r.qty} carried</span>` : ''}
              ${/illegal/.test(r.flags || '') ? '<span class="chip tag-illegal">illegal</span>' : ''}
              ${/legendary/.test(r.flags || '') ? '<span class="chip" style="color:var(--yel);border-color:var(--yel)">legendary</span>' : ''}
            </div>
          </div>
        </div>
        ${stats.length ? `<div class="ivstats">${stats.map(s => this._esc(s)).join('&nbsp;&nbsp; ')}</div>` : ''}
        <p class="ivdesc">${this._esc(r.desc || 'No further detail.')}</p>
        <div class="ivacts">${acts.map(([cmd, label, cls, note]) =>
          `<button class="btn ${cls || ''}" data-cmd="${this._esc(cmd)}">${label}</button>${note ? `<span class="ivnote">${note}</span>` : ''}`).join('')}</div>`;
      paint(det);
      det.querySelectorAll('[data-cmd]').forEach(b => b.onclick = () => { this.emit('act', b.dataset.cmd); this.closeModal(); });
    };

    root.querySelectorAll('.ivslot').forEach(s => {
      s.onclick = () => { if (s.dataset.key) select(s.dataset.key); };
      s.ondblclick = () => { const b = det.querySelector('.ivacts .pri'); if (b) b.click(); };
    });
    const first = root.querySelector('.ivslot.has');
    if (first) select(first.dataset.key);
  }

  sheet(state) {
    const p = state.player;
    const st = Object.keys(p.stats).map(k => {
      const d = p.stats[k] - (p.baseStats[k] ?? p.stats[k]);
      return `<div class="statline"><span class="k">${k.toUpperCase()}</span><span><b>${p.stats[k]}</b>${d ? ` <span class="d">+${d}</span>` : ''}</span></div>`;
    }).join('');
    const sk = Object.entries(p.skills).map(([k, v]) => `<div class="statline"><span class="k">${k}</span><b>${v}</b></div>`).join('');
    const ef = p.effects.map(e => `<span class="chip">${this._esc(e.name)} ${e.secs}s</span>`).join(' ') || '<span style="color:var(--dim)">none</span>';
    this._modal('Character Sheet', `
      <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px">
        <canvas id="portrait" width="96" height="96" style="image-rendering:pixelated;background:#0b0c15;border:1px solid var(--line2);border-radius:8px"></canvas>
        <div><div style="font-size:17px;font-weight:800">${this._esc(p.name)}</div>
          <div style="color:var(--cyan);font-family:var(--mono);font-size:12px">lv ${p.level} ${this._esc(p.archetype)}${p.title ? ' · ' + this._esc(p.title) : ''}</div>
          <div style="color:var(--dim);font-size:11px;margin-top:3px">XP ${p.xp} / ${p.xpNext}  ·  ¥${p.money.toLocaleString()} on hand</div></div>
      </div>
      <div class="sheet">
      <div><div class="sect">Attributes ${p.unspent ? `<span class="chip" style="color:var(--yel)">${p.unspent} to spend — type <kbd>spend body</kbd></span>` : ''}</div>${st}
        <div class="sect" style="margin-top:14px">Vitals</div>
        <div class="statline"><span class="k">HP</span><b>${p.hp}/${p.maxHp}</b></div>
        <div class="statline"><span class="k">HEAT</span><b>${p.energy}/${p.maxEnergy}</b></div>
        <div class="statline"><span class="k">ARMOR</span><b>${p.ac}</b></div>
        <div class="statline"><span class="k">STREET CRED</span><b>${p.cred}</b></div>
        <div class="statline"><span class="k">NCPD HEAT</span><b>${p.wanted}/100</b></div>
        <div class="statline"><span class="k">BANK</span><b>¥${p.bank}</b></div>
      </div>
      <div><div class="sect">Skills</div>${sk}
        <div class="sect" style="margin-top:14px">Buffs</div><div>${ef}</div>
        <div class="sect" style="margin-top:14px">Record</div>
        <div class="statline"><span class="k">KILLS</span><b>${p.kills}</b></div>
        <div class="statline"><span class="k">DEATHS</span><b>${p.deaths}</b></div>
        <div class="statline"><span class="k">HUNGER / THIRST</span><b>${p.hunger} / ${p.thirst}</b></div>
      </div></div>`);
    const c = this.el('modalRoot').querySelector('#portrait');
    if (c) c.getContext('2d').drawImage(actorCanvas(p.archetype, 96), 0, 0);
  }

  /* paper-doll wear / gear screen */
  gear(state) {
    const eq = state.equipment || {};
    const p = state.player;
    const WEAR = ['head', 'eyes', 'face', 'neck', 'torso', 'back', 'arms', 'hands', 'waist', 'legs', 'feet'];
    const HOLD = ['wield', 'held'];
    const IMPL = ['implant_neural', 'implant_ocular', 'implant_arm', 'implant_skeleton', 'implant_dermal'];
    const slot = (s, big) => {
      const it = eq[s];
      const label = s.replace('implant_', '');
      const cmd = it ? (s.startsWith('implant_') ? 'uninstall ' + it.kw : 'remove ' + it.kw) : '';
      return `<div class="dslot ${it ? 'on' : ''} ${big ? 'big' : ''}" ${it ? `data-cmd="${cmd}" title="click to ${s.startsWith('implant_') ? 'have removed at a ripperdoc' : 'take off'}"` : ''}>
        <span class="dl">${label}</span>
        ${it ? `<canvas width="40" height="40" data-icon="${it.icon}" data-seed="${this._esc(it.name)}"></canvas><span class="dn">${this._esc(it.name)}</span>`
             : `<span class="de">—</span>`}</div>`;
    };
    const root = this._modal(`Wear &amp; Gear  <span class="chip">AC ${p.ac}</span>`, `
      <div class="doll">
        <div class="dcol">${WEAR.slice(0, 6).map(s => slot(s)).join('')}</div>
        <div class="dmid">
          <canvas id="dollportrait" width="130" height="150"></canvas>
          <div class="dhand">${HOLD.map(s => slot(s, 1)).join('')}</div>
        </div>
        <div class="dcol">${WEAR.slice(6).map(s => slot(s)).join('')}</div>
      </div>
      <div class="sect" style="margin-top:14px">Cyberware</div>
      <div class="grid">${IMPL.map(s => slot(s)).join('')}</div>
      <p style="color:var(--dim);font-size:11px;margin-top:10px">Equip from your <b>inventory</b> (I). Click a worn item to take it off; chrome needs a ripperdoc — walk into one and it's handled.</p>`);
    root.querySelectorAll('canvas[data-icon]').forEach(c => c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, 40, c.dataset.seed || ''), 0, 0));
    const dp = root.querySelector('#dollportrait');
    if (dp) dp.getContext('2d').drawImage(actorCanvas(p.archetype, 130), 0, 10);
    root.querySelectorAll('.dslot[data-cmd]').forEach(s => s.onclick = () => this.emit('act', s.dataset.cmd));
  }

  mapModal(state) {
    const here = (state && state.room) || {};
    const z = here.z != null ? here.z : ((state && state.map && state.map.z) || 0);
    const root = this._modal(
      'City Atlas  <span class="chip">fog of war</span>',
      '<div class="wm-wrap"></div>', 'mapbox');
    const wrap = root.querySelector('.wm-wrap');
    this._wmCleanup = openWorldMap(wrap, {
      hereVnum: here.vnum || 0,
      hereZ: z,
      zoneSlug: here.zone || '',
    });
  }

  helpModal() {
    this._modal('How to play', `<div style="line-height:1.7">
      <p><b>Move</b> with <kbd>WASD</kbd> / arrow keys (<kbd>Q E Z C</kbd> for diagonals), or click a tile.
        Walk into a glowing edge to go through an exit.</p>
      <p><b>Interact</b>: walk into someone or something, or press <kbd>Space</kbd> facing them, or click their card on the right.
        Hostiles → fight. Keepers → shop. Fixers → jobs.</p>
      <p><b>Anything the MUD understands</b> works in the command box: <kbd>look terminal</kbd>, <kbd>hack atm</kbd>,
        <kbd>board</kbd>, <kbd>train hacking</kbd>, <kbd>talk rogue about work</kbd>, <kbd>rest</kbd>, <kbd>recall</kbd>…</p>
      <p><kbd>I</kbd> inventory · <kbd>C</kbd> character · <kbd>M</kbd> map · <kbd>Enter</kbd> command box · <kbd>Esc</kbd> close</p>
      <p style="color:var(--dim)">Same world, same character as the BBS terminal at <a href="/">bbs.thugs.red</a>.</p>
    </div>`);
  }

  portalChoice(exits) {
    const root = this._modal('Which way?', '<div style="display:flex;gap:10px;flex-wrap:wrap">' +
      exits.map(e => `<button class="btn pri" data-dir="${e.dir}">${e.dir.toUpperCase()} — ${this._esc(e.name)}</button>`).join('') + '</div>');
    root.querySelectorAll('[data-dir]').forEach(b => b.onclick = () => { this.emit('act', b.dataset.dir); this.closeModal(); });
  }

  /* ---- loot screen ---- */
  loot(state) {
    const items = (state && state.room && state.room.items) || [];
    const body = items.length
      ? `<div style="margin-bottom:12px"><button class="btn pri" id="lootall">Take everything</button></div>
         <div class="lootgrid">${items.map(it => `
           <div class="lootcard" data-kw="${this._esc(it.kw)}">
             <canvas width="46" height="46" data-icon="${it.icon}" data-seed="${this._esc(it.name)}"></canvas>
             <div class="ln">${this._esc(it.name)}</div>
             <div style="font-size:10px;color:var(--dim)">${it.type} · ¥${(it.value || 0).toLocaleString()}</div>
             <button class="btn sm" style="margin-top:6px;width:100%">Take</button>
           </div>`).join('')}</div>`
      : '<p style="color:var(--dim)">Nothing on the ground here. Drop something, or kill something.</p>';
    const root = this._modal('Loot', body);
    root.querySelectorAll('canvas[data-icon]').forEach(c => c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, 46, c.dataset.seed || ''), 0, 0));
    const all = root.querySelector('#lootall');
    if (all) all.onclick = () => { this.emit('act', 'get all'); this.closeModal(); };
    root.querySelectorAll('.lootcard').forEach(card => card.querySelector('button').onclick = () => {
      this.emit('act', 'get ' + card.dataset.kw);
      card.style.opacity = 0.35; card.querySelector('button').disabled = true;
    });
  }

  /* ---- enemy inspect ---- */
  enemyCard(mob, lines) {
    const tags = [];
    if (mob.boss) tags.push('<span class="chip" style="color:var(--yel);border-color:var(--yel)">boss</span>');
    if (mob.hostile) tags.push('<span class="chip tag-illegal">hostile</span>');
    const root = this._modal(this._esc(mob.name), `
      <div style="display:flex;gap:16px;align-items:flex-start">
        <canvas id="mobport" width="96" height="96" style="image-rendering:pixelated;background:radial-gradient(circle at 50% 40%,#231018,#0b0c15);border:1px solid var(--line2);border-radius:8px"></canvas>
        <div style="flex:1;min-width:0">
          <div style="font-family:var(--mono);color:var(--cyan);font-size:12px">lv ${mob.level} · ${this._esc(mob.faction)}</div>
          <div style="height:6px;margin:8px 0;border-radius:3px;background:#2a0f16;overflow:hidden"><i style="display:block;height:100%;background:var(--red);width:${Math.round((mob.hpPct ?? 1) * 100)}%"></i></div>
          <div>${tags.join(' ')}</div>
          <div style="margin-top:10px;font-family:var(--mono);font-size:12px;line-height:1.5">
            ${(lines || []).map(l => pipeHtml(l === '' ? ' ' : l)).join('<br>')}
          </div>
        </div>
      </div>
      <div class="row" style="margin-top:14px">
        <button class="btn pri" id="mobatk">Attack</button>
        <button class="btn" id="mobtalk">Talk</button>
      </div>`);
    const c = root.querySelector('#mobport');
    if (c) c.getContext('2d').drawImage(actorCanvas(mob.sprite || 'civ', 96), 0, 0);
    root.querySelector('#mobatk').onclick = () => { this.emit('act', 'kill ' + mob.kw); this.closeModal(); };
    root.querySelector('#mobtalk').onclick = () => { this.emit('act', 'talk ' + mob.kw); this.closeModal(); };
  }

  /* ---- online / SMS ---- */
  social(state, inbox) {
    const online = (state && state.online) || [];
    inbox = inbox || [];
    const rows = online.map(o => `
      <div class="online-row">
        <span class="dot"></span>
        <div class="oi"><b>${this._esc(o.name)}${o.me ? ' <span style="color:var(--dim)">(you)</span>' : ''}</b>
          <span>lv ${o.level} · ${this._esc(o.archetype)} · ${this._esc(o.where)}${o.idle > 90 ? ' · idle ' + Math.floor(o.idle / 60) + 'm' : ''}</span></div>
        ${o.me ? '' : `<button class="btn sm" data-to="${this._esc(o.name)}">Message</button>`}
      </div>`).join('') || '<p style="color:var(--dim)">Nobody else is jacked in right now.</p>';
    const thread = inbox.map(m => `
      <div class="sms-msg ${m.mine ? 'me' : 'them'}">
        <span class="who">${m.mine ? 'you' : this._esc(m.from)} · ${m.at}</span>${this._esc(m.body)}
      </div>`).join('') || '<p style="color:var(--dim);font-size:11px">No messages yet.</p>';
    const root = this._modal('Online &amp; Messages', `
      <div class="sect">Runners online (${online.length})</div>
      <div style="max-height:180px;overflow:auto">${rows}</div>
      <div class="sect" style="margin-top:14px">Messages</div>
      <div class="sms-list">${thread}</div>
      <div class="field" style="display:flex;gap:8px;margin-top:8px">
        <input id="smsto" placeholder="to (handle)" style="width:130px;background:#0b0c15;border:1px solid var(--line2);border-radius:var(--r-s);padding:.5em;color:var(--ink);font-family:var(--mono)">
        <input id="smsbody" placeholder="message…" maxlength="280" style="flex:1;background:#0b0c15;border:1px solid var(--line2);border-radius:var(--r-s);padding:.5em;color:var(--ink);font-family:var(--mono)">
        <button class="btn pri" id="smssend">Send</button>
      </div>`);
    const to = root.querySelector('#smsto'), body = root.querySelector('#smsbody');
    root.querySelectorAll('[data-to]').forEach(b => b.onclick = () => { to.value = b.dataset.to; body.focus(); });
    const send = () => {
      const t = to.value.trim(), bd = body.value.trim();
      if (!t || !bd) return;
      body.value = '';
      this.emit('sms', { to: t, body: bd });
    };
    root.querySelector('#smssend').onclick = send;
    body.addEventListener('keydown', e => { if (e.key === 'Enter') send(); e.stopPropagation(); });
    to.addEventListener('keydown', e => e.stopPropagation());
    const list = root.querySelector('.sms-list'); if (list) list.scrollTop = list.scrollHeight;
  }

  /* ---- sound + music mixer popover ---- */
  soundIcon(on) { this.el('btnSnd').textContent = on ? '\u{1F50A}' : '\u{1F507}'; this._sndOn = on; }
  _closeMixer() {
    if (this._npTimer) { clearInterval(this._npTimer); this._npTimer = null; }
    const m = this.el('game').querySelector('.mix'); if (m) m.remove();
  }
  _toggleMixer() {
    if (this.el('game').querySelector('.mix')) { this._closeMixer(); return; }
    audio.unlock();
    const mix = audio.getMix ? audio.getMix() : { music: 0.32, sfx: 1, amb: 0.7, musicOn: true };
    const on = this._sndOn !== false;
    const pc = (v) => Math.round((v || 0) * 100);
    const wrap = document.createElement('div');
    wrap.className = 'mix';
    wrap.innerHTML = `
      <h4>Sound</h4>
      <div class="seg">
        <button data-k="master" class="${on ? 'on' : ''}">Sound ${on ? 'ON' : 'OFF'}</button>
        <button data-k="music" class="${mix.musicOn ? 'on' : ''}">Music ${mix.musicOn ? 'ON' : 'OFF'}</button>
      </div>
      <div class="mrow"><label>Music</label><input type="range" min="0" max="100" value="${pc(mix.music)}" data-b="music"></div>
      <div class="mrow"><label>Effects</label><input type="range" min="0" max="100" value="${pc(mix.sfx)}" data-b="sfx"></div>
      <div class="mrow"><label>Ambient</label><input type="range" min="0" max="100" value="${pc(mix.amb)}" data-b="amb"></div>
      <p style="font-size:10px;color:var(--dim);margin:8px 0 0">Music engine</p>
      <div class="seg" style="margin-top:6px">
        <button data-m="gen" class="${mix.musicMode === 'chip' ? '' : 'on'}">Generated</button>
        <button data-m="chip" class="${mix.musicMode === 'chip' ? 'on' : ''}">Chiptune</button>
      </div>
      <p class="npchip" style="font-size:10px;color:var(--cyan);margin:6px 0 0;min-height:12px"></p>
      <p style="font-size:10px;color:var(--dim);margin:6px 0 0">music sits low so effects stay clear</p>`;
    this.el('game').appendChild(wrap);
    wrap.querySelectorAll('input[data-b]').forEach(inp => inp.addEventListener('input', () => {
      audio.setMix({ [inp.dataset.b]: (+inp.value) / 100 });
    }));
    const syncNp = () => {
      const el = wrap.querySelector('.npchip'); if (!el) return;
      const m = audio.nowPlayingTrack ? audio.nowPlayingTrack() : null;
      el.textContent = m ? ('♪ ' + (m.title || '') + (m.artist ? ' — ' + m.artist : '')) : '';
    };
    wrap.querySelectorAll('button[data-m]').forEach(b => b.onclick = () => {
      const mode = b.dataset.m;
      if ((audio.getMix().musicMode || 'gen') === mode) return;
      audio.setMix({ musicMode: mode });
      wrap.querySelectorAll('button[data-m]').forEach(x => x.classList.toggle('on', x.dataset.m === mode));
      syncNp();
    });
    if (this._npTimer) clearInterval(this._npTimer);
    this._npTimer = setInterval(syncNp, 1500);
    syncNp();
    wrap.querySelector('[data-k="master"]').onclick = (e) => {
      this.emit('sound');
      const nowOn = this._sndOn !== false;
      e.target.textContent = 'Sound ' + (nowOn ? 'ON' : 'OFF'); e.target.classList.toggle('on', nowOn);
    };
    wrap.querySelector('[data-k="music"]').onclick = (e) => {
      const v = !(audio.getMix().musicOn);
      audio.setMix({ musicOn: v });
      this.emit('music-toggle', v);
      e.target.textContent = 'Music ' + (v ? 'ON' : 'OFF'); e.target.classList.toggle('on', v);
    };
    // close on outside click
    setTimeout(() => {
      const off = ev => { if (!wrap.contains(ev.target) && ev.target.id !== 'btnSnd') { this._closeMixer(); removeEventListener('pointerdown', off); } };
      addEventListener('pointerdown', off);
    }, 0);
  }
}
