/* items.js - the Hackers-MUD item database viewer / showcase. */
import { iconCanvas } from './sprites.js';

const root = document.getElementById('dex');

/* self-contained styles for the loader + progress bar (CSP allows inline) */
(() => {
  const s = document.createElement('style');
  s.textContent = `
  .dexload{max-width:520px;margin:16vh auto 0;padding:28px;text-align:center;
    border:1px solid var(--line2);border-radius:12px;background:linear-gradient(180deg,#14152400,#141524)}
  .dexload .brand{font-size:22px;margin-bottom:18px}
  .lbar{height:8px;border-radius:5px;background:#0b0c15;border:1px solid var(--line2);overflow:hidden;margin:6px 0 12px}
  .lbar i{display:block;height:100%;width:4%;background:linear-gradient(90deg,var(--red),var(--cyan));
    box-shadow:0 0 12px -2px var(--cyan);transition:width .25s}
  .lmsg{font-family:var(--mono);font-size:12px;color:var(--mut)}
  .dexbar{height:3px;background:#0b0c15;margin:0 0 12px;border-radius:2px;overflow:hidden;display:none}
  .dexbar i{display:block;height:100%;width:0;background:var(--cyan);box-shadow:0 0 8px var(--cyan);transition:width .12s}
  .dexcount{color:var(--dim);font-family:var(--mono);font-size:11px;padding:8px 0 40px}`;
  document.head.appendChild(s);
})();
const TYPES = ['weapon', 'armor', 'implant', 'computer', 'gear', 'gadget', 'food', 'drink', 'drug', 'light', 'container', 'currency', 'material', 'lore', 'junk'];

/* ---------- loading screen with a real progress bar ---------- */
function loadScreen() {
  root.innerHTML = `
  <div class="dexload">
    <div class="brand">HACKERS-MUD<small>ITEM DATABASE</small></div>
    <div class="lbar"><i id="lfill"></i></div>
    <div class="lmsg" id="lmsg">connecting…</div>
  </div>`;
}
function prog(pct, msg) {
  const f = document.getElementById('lfill'), m = document.getElementById('lmsg');
  if (f) f.style.width = Math.max(3, Math.min(100, pct)) + '%';
  if (m && msg) m.textContent = msg;
}
function loadError(msg) {
  root.innerHTML = `<div class="dexload">
    <div class="brand">HACKERS-MUD<small>ITEM DATABASE</small></div>
    <div class="lmsg" style="color:var(--red)">${msg}</div>
    <button class="btn pri" id="lretry" style="margin-top:14px">Retry</button></div>`;
  document.getElementById('lretry').onclick = boot;
}

boot();
async function boot() {
  loadScreen();
  let items;
  try {
    items = await fetchItems();
  } catch (e) {
    return loadError((e && e.message) || 'Could not reach the catalogue. Check your connection and retry.');
  }
  if (!items || !items.length) return loadError('The catalogue came back empty.');
  await renderChunked(items);
}

/* stream the JSON so we can show download progress on slow links */
async function fetchItems() {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 30000);
  let res;
  try {
    res = await fetch('/api/mud/itemdex', { credentials: 'same-origin', signal: ctl.signal, headers: { Accept: 'application/json' } });
  } finally { clearTimeout(timer); }
  if (!res.ok) throw new Error('server said ' + res.status);

  const total = +(res.headers.get('content-length') || 0);
  let text = '';
  if (res.body && res.body.getReader && total > 0) {
    const reader = res.body.getReader();
    const dec = new TextDecoder();
    let got = 0;
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      got += value.length;
      text += dec.decode(value, { stream: true });
      prog(5 + (got / total) * 55, `downloading catalogue… ${(got / 1024) | 0} KB`);
    }
    text += dec.decode();
  } else {
    prog(30, 'downloading catalogue…');
    text = await res.text();
    prog(60, 'downloading catalogue…');
  }
  const data = JSON.parse(text);
  if (!data || !data.ok) throw new Error((data && data.error) || 'bad response');
  return data.items;
}

/* build cards in animation-frame batches so the page never freezes */
function renderChunked(items) {
  return new Promise(resolve => {
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
      <div class="dexbar"><i id="dbfill"></i></div>
      <div id="dgrid" class="dexgrid"></div>
      <div id="dcount" class="dexcount"></div>
    </div>`;

    const grid = document.getElementById('dgrid');
    const bar = document.getElementById('dbfill');
    const barWrap = document.querySelector('.dexbar');
    const count = document.getElementById('dcount');

    const filtered = () => {
      let list = items.filter(it =>
        (!state.type || it.type === state.type) &&
        (!state.q || (it.name + ' ' + it.desc + ' ' + it.type).toLowerCase().includes(state.q)));
      list.sort((a, b) => state.sort === 'name' ? a.name.localeCompare(b.name)
        : state.sort === 'value' ? b.value - a.value
        : state.sort === 'lvl' ? b.lvl - a.lvl : a.vnum - b.vnum);
      return list;
    };

    let drawToken = 0;
    const draw = (announceDone) => {
      const my = ++drawToken;
      const list = filtered();
      grid.innerHTML = '';
      count.textContent = list.length + ' shown';
      if (!list.length) { grid.innerHTML = '<p style="color:var(--dim);padding:20px">Nothing matches.</p>'; if (announceDone) resolve(); return; }
      barWrap.style.display = 'block';
      let i = 0;
      const CH = 24;
      const step = () => {
        if (my !== drawToken) return;                 // a newer filter started
        const frag = document.createDocumentFragment();
        for (let n = 0; n < CH && i < list.length; n++, i++) frag.appendChild(card(list[i]));
        grid.appendChild(frag);
        // draw icons for the batch we just added
        grid.querySelectorAll('canvas[data-icon]:not([data-done])').forEach(c => {
          c.dataset.done = '1';
          try { c.getContext('2d').drawImage(iconCanvas(c.dataset.icon, 56), 0, 0); } catch (_) {}
        });
        bar.style.width = Math.round((i / list.length) * 100) + '%';
        if (i < list.length) requestAnimationFrame(step);
        else { setTimeout(() => { barWrap.style.display = 'none'; }, 250); if (announceDone) resolve(); }
      };
      requestAnimationFrame(step);
    };

    document.getElementById('dq').addEventListener('input', e => { state.q = e.target.value.trim().toLowerCase(); draw(); });
    document.getElementById('dtype').addEventListener('change', e => { state.type = e.target.value; draw(); });
    document.getElementById('dsort').addEventListener('change', e => { state.sort = e.target.value; draw(); });
    draw(true);
  });
}

function card(it) {
  const el = document.createElement('div');
  el.className = 'dexcard' + (/illegal/.test(it.flags) ? ' ill' : '') + (/legendary/.test(it.flags) ? ' leg' : '');
  const stats = [];
  if (it.dmg) stats.push('⚔ ' + it.dmg);
  if (it.armor) stats.push('\u{1F6E1} ' + it.armor);
  if (it.mods) stats.push(Object.entries(it.mods).map(([k, v]) => `${k} ${v > 0 ? '+' : ''}${v}`).join(' '));
  if (it.eff) stats.push(it.eff.join(', '));
  if (it.lvl > 1) stats.push('lv ' + it.lvl);
  el.innerHTML = `
    <canvas width="56" height="56" data-icon="${esc(it.icon)}"></canvas>
    <div class="di">
      <b>${esc(it.name)}</b>
      <div class="dm">${esc(it.type)}${it.slot ? ' · &lt;' + esc(it.slot.replace('implant_', '')) + '&gt;' : ''} · ¥${it.value.toLocaleString()} · ${it.weight}kg</div>
      <div class="ds">${stats.map(esc).join(' &nbsp; ')}</div>
      <p>${esc(it.desc)}</p>
      ${/illegal/.test(it.flags) ? '<span class="chip tag-illegal">illegal</span>' : ''}
      ${/legendary/.test(it.flags) ? '<span class="chip" style="color:var(--yel);border-color:var(--yel)">legendary</span>' : ''}
    </div>`;
  return el;
}

const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
