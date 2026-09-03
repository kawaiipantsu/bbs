/* items.js - the Hackers-MUD item database viewer / showcase. */
import { api } from './net.js';
import { iconCanvas } from './sprites.js';

const root = document.getElementById('dex');
const TYPES = ['weapon', 'armor', 'implant', 'computer', 'gear', 'gadget', 'food', 'drink', 'drug', 'light', 'container', 'currency', 'material', 'lore', 'junk'];

boot();
async function boot() {
  const r = await api.itemdex();
  if (!r || !r.ok) { root.innerHTML = '<div class="loading">could not load the catalogue.</div>'; return; }
  render(r.items);
}

function render(items) {
  const state = { q: '', type: '', sort: 'vnum' };
  root.innerHTML = `
  <div class="dexwrap">
    <header class="dexhead">
      <div class="brand">HACKERS-MUD<small>ITEM DATABASE — ${items.length} entries</small></div>
      <div class="dexctl">
        <input id="dq" placeholder="search name / description…" autocomplete="off">
        <select id="dtype"><option value="">all types</option>${TYPES.map(t => `<option>${t}</option>`).join('')}</select>
        <select id="dsort">
          <option value="vnum">by id</option><option value="name">A–Z</option>
          <option value="value">by value</option><option value="lvl">by level</option>
        </select>
      </div>
      <a class="btn sm" href="/hackers-mud/">▶ play</a>
    </header>
    <div id="dgrid" class="dexgrid"></div>
  </div>`;

  const grid = document.getElementById('dgrid');
  const draw = () => {
    let list = items.filter(it =>
      (!state.type || it.type === state.type) &&
      (!state.q || (it.name + ' ' + it.desc + ' ' + it.type).toLowerCase().includes(state.q)));
    list.sort((a, b) => state.sort === 'name' ? a.name.localeCompare(b.name)
      : state.sort === 'value' ? b.value - a.value
      : state.sort === 'lvl' ? b.lvl - a.lvl : a.vnum - b.vnum);
    grid.innerHTML = '';
    for (const it of list) {
      const card = document.createElement('div');
      card.className = 'dexcard' + (/illegal/.test(it.flags) ? ' ill' : '') + (/legendary/.test(it.flags) ? ' leg' : '');
      const stats = [];
      if (it.dmg) stats.push(`⚔ ${it.dmg}`);
      if (it.armor) stats.push(`🛡 ${it.armor}`);
      if (it.mods) stats.push(Object.entries(it.mods).map(([k, v]) => `${k} ${v > 0 ? '+' : ''}${v}`).join(' '));
      if (it.eff) stats.push(it.eff.join(', '));
      if (it.lvl > 1) stats.push(`lv ${it.lvl}`);
      card.innerHTML = `
        <canvas width="56" height="56" data-icon="${it.icon}"></canvas>
        <div class="di">
          <b>${esc(it.name)}</b>
          <div class="dm">${it.type}${it.slot ? ' · &lt;' + it.slot.replace('implant_', '') + '&gt;' : ''}
            · ¥${it.value.toLocaleString()} · ${it.weight}kg</div>
          <div class="ds">${stats.map(esc).join(' &nbsp; ')}</div>
          <p>${esc(it.desc)}</p>
          ${/illegal/.test(it.flags) ? '<span class="chip tag-illegal">illegal</span>' : ''}
          ${/legendary/.test(it.flags) ? '<span class="chip" style="color:var(--yel);border-color:var(--yel)">legendary</span>' : ''}
        </div>`;
      grid.appendChild(card);
    }
    grid.querySelectorAll('canvas[data-icon]').forEach(c =>
      c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, 56), 0, 0));
    if (!list.length) grid.innerHTML = '<p style="color:var(--dim);padding:20px">Nothing matches.</p>';
  };
  document.getElementById('dq').addEventListener('input', e => { state.q = e.target.value.trim().toLowerCase(); draw(); });
  document.getElementById('dtype').addEventListener('change', e => { state.type = e.target.value; draw(); });
  document.getElementById('dsort').addEventListener('change', e => { state.sort = e.target.value; draw(); });
  draw();
}

const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
