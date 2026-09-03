/* ui.js - DOM chrome: top bar, side panels, log, modals. Renders from state. */
import { iconCanvas, actorCanvas } from './sprites.js';

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
          <button class="btn" data-modal="sheet" title="Character (C)">\u{1F464}</button>
          <button class="btn" data-modal="map" title="Map (M)">\u{1F5FA}️</button>
          <button class="btn" data-modal="help" title="Help">?</button>
          <button class="btn" id="btnSnd" title="Sound">\u{1F50A}</button>
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
    this.el('btnSnd').onclick = () => this.emit('sound');

    addEventListener('keydown', e => {
      if (/input|textarea/i.test(e.target.tagName)) return;
      if (e.key === 'i' || e.key === 'I') this.emit('modal', 'inv');
      else if (e.key === 'm' || e.key === 'M') this.emit('modal', 'map');
      else if (e.key === 'c' || e.key === 'C') this.emit('modal', 'sheet');
      else if (e.key === 'Enter') { e.preventDefault(); this.el('cmdIn').focus(); }
      else if (e.key === 'Escape') this.closeModal();
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

  soundIcon(on) { this.el('btnSnd').textContent = on ? '\u{1F50A}' : '\u{1F507}'; }

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
    }
    for (const op of r.players) {
      const c = add('civ', op.name, `lv ${op.level} · ${op.archetype}` + (op.title ? ' · ' + op.title : ''), 'look', 'look ' + op.name.toLowerCase());
      c.getContext('2d').drawImage(actorCanvas('civ', 30), 0, 0);
    }
    if (r.items.length) box.insertAdjacentHTML('beforeend', '<div class="sect">On the ground</div>');
    for (const it of r.items) {
      const c = add(it.icon, it.name, 'item', 'grab', 'get ' + it.kw);
      c.getContext('2d').drawImage(iconCanvas(it.icon, 30), 0, 0);
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
  closeModal() { this.el('modalRoot').innerHTML = ''; }
  _modal(title, bodyHtml) {
    this.el('modalRoot').innerHTML = `<div class="modal"><div class="box">
      <h3>${title}<button class="x">&times;</button></h3><div class="body">${bodyHtml}</div></div></div>`;
    const root = this.el('modalRoot');
    root.querySelector('.x').onclick = () => this.closeModal();
    root.querySelector('.modal').onclick = e => { if (e.target.classList.contains('modal')) this.closeModal(); };
    return root;
  }

  inventory(state) {
    const inv = state.inventory, eq = state.equipment;
    const cell = (it, equipped) => `<div class="slot ${equipped ? 'eq' : ''}" data-cmd="${it._cmd}">
      <canvas width="44" height="44" data-icon="${it.icon}"></canvas>
      <div class="nm">${this._esc(it.name)}${it.illegal ? ' <span style="color:var(--red)">⚠</span>' : ''}</div>
      ${it.qty > 1 ? `<div class="qn">x${it.qty}</div>` : ''}</div>`;
    const eqList = Object.entries(eq).map(([slot, it]) => {
      it._cmd = 'uninstall ' + it.kw; it._cmd = slot.startsWith('implant_') ? 'uninstall ' + it.kw : 'remove ' + it.kw;
      return `<div class="slot eq" data-cmd="${it._cmd}"><canvas width="44" height="44" data-icon="${it.icon}"></canvas>
        <div class="nm">&lt;${slot.replace('implant_', '')}&gt;<br>${this._esc(it.name)}</div></div>`;
    }).join('') || '<p style="color:var(--dim)">Nothing equipped.</p>';
    const invList = inv.map(it => {
      it._cmd = ({ weapon: 'wield ', armor: 'wear ', implant: 'implant ', computer: 'hold ',
        food: 'eat ', drink: 'drink ', drug: 'inject ' }[it.type] || 'use ') + it.kw;
      return cell(it, false);
    }).join('') || '<p style="color:var(--dim)">Your pack is empty.</p>';
    const root = this._modal(`Inventory  <span class="chip">${state.player.carry} / ${state.player.maxCarry} kg</span>`,
      `<div class="sect">Worn / wired</div><div class="grid">${eqList}</div>
       <div class="sect" style="margin-top:16px">Carrying</div><div class="grid">${invList}</div>`);
    root.querySelectorAll('canvas[data-icon]').forEach(c => c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, 44), 0, 0));
    root.querySelectorAll('.slot[data-cmd]').forEach(s => s.onclick = () => { this.emit('act', s.dataset.cmd); });
  }

  sheet(state) {
    const p = state.player;
    const st = Object.keys(p.stats).map(k => {
      const d = p.stats[k] - (p.baseStats[k] ?? p.stats[k]);
      return `<div class="statline"><span class="k">${k.toUpperCase()}</span><span><b>${p.stats[k]}</b>${d ? ` <span class="d">+${d}</span>` : ''}</span></div>`;
    }).join('');
    const sk = Object.entries(p.skills).map(([k, v]) => `<div class="statline"><span class="k">${k}</span><b>${v}</b></div>`).join('');
    const ef = p.effects.map(e => `<span class="chip">${this._esc(e.name)} ${e.secs}s</span>`).join(' ') || '<span style="color:var(--dim)">none</span>';
    this._modal('Character Sheet', `<div class="sheet">
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
  }

  mapModal(state) {
    const m = state.map;
    const root = this._modal('Local Map  <span class="chip">' + this._esc(m.zone || '') + ' — level ' + m.z + '</span>', '<canvas id="bigmap" width="520" height="440" style="width:100%;image-rendering:pixelated;background:#05060c;border-radius:8px"></canvas><p style="color:var(--dim);font-size:11px;margin-top:8px">Red = you. Filled = explored. Use exits or arrow keys to move.</p>');
    const c = root.querySelector('#bigmap'), ctx = c.getContext('2d');
    const cell = 34, ox = c.width / 2, oy = c.height / 2;
    for (const k of m.cells) {
      const gx = ox + (k.x - m.cx) * cell, gy = oy - (k.y - m.cy) * cell;
      ctx.fillStyle = k.here ? '#ff2d55' : k.visited ? '#1b2440' : '#0e1220';
      ctx.fillRect(gx - 12, gy - 12, 24, 24);
      ctx.strokeStyle = k.here ? '#ff6a88' : '#2b3352'; ctx.strokeRect(gx - 12, gy - 12, 24, 24);
      ctx.strokeStyle = '#2b3352'; ctx.lineWidth = 2;
      for (const d of k.exits) {
        ctx.beginPath();
        if (d === 'n') { ctx.moveTo(gx, gy - 12); ctx.lineTo(gx, gy - 17); }
        else if (d === 's') { ctx.moveTo(gx, gy + 12); ctx.lineTo(gx, gy + 17); }
        else if (d === 'e') { ctx.moveTo(gx + 12, gy); ctx.lineTo(gx + 17, gy); }
        else if (d === 'w') { ctx.moveTo(gx - 12, gy); ctx.lineTo(gx - 17, gy); } else continue;
        ctx.stroke();
      }
      if (k.visited && !k.here) { ctx.fillStyle = '#7a86b8'; ctx.font = '7px ui-monospace'; ctx.fillText((k.name || '').slice(0, 10), gx - 11, gy + 20); }
    }
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
}
