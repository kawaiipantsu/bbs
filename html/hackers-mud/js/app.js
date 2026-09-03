/* app.js - boot, auth screens, and the game loop for the Hackers-MUD client. */
import { api, S } from './net.js';
import { audio } from './audio.js';
import { Scene } from './scene.js';
import { UI, pipeHtml } from './ui.js';

const root = document.getElementById('app');
let ui, scene, poll, inflight = false, sndOn = true;

/* first gesture unlocks audio */
function armAudio() {
  audio.setOn(sndOn); audio.unlock();
  removeEventListener('pointerdown', armAudio); removeEventListener('keydown', armAudio);
}
addEventListener('pointerdown', armAudio); addEventListener('keydown', armAudio);
try { sndOn = localStorage.getItem('hm_snd') !== '0'; } catch (_) {}

boot();

async function boot() {
  screenLoading('connecting to the grid…');
  const w = await api.whoami();
  if (w && w.authed) {
    if (!w.hasChar) return screenArchetype();
    const st = await api.state();
    if (st.needArchetype) return screenArchetype();
    if (st.ok) return startGame(st.state);
  }
  screenLogin();
}

/* ---------- screens ---------- */
function screenLoading(msg) {
  root.innerHTML = `<div class="loading">${msg}</div>`;
}

function screenLogin(err) {
  root.innerHTML = `
  <div class="screen"><div class="card">
    <div class="brand">HACKERS-MUD<small>NIGHT CITY</small></div>
    <h2>Jack in</h2>
    <p class="sub">Use your <b>THUGS(red) BBS</b> handle. Same account, same character.</p>
    <form id="lf">
      <div class="field"><label>Handle</label><input name="handle" autocomplete="username" autofocus></div>
      <div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password"></div>
      <div class="msg" id="lm">${err ? err : ''}</div>
      <div class="row"><button class="btn pri" type="submit">Connect</button></div>
    </form>
    <div class="foot">No account? <a href="/">Register on the BBS</a> first — then come back.</div>
  </div></div>`;
  root.querySelector('#lf').onsubmit = async e => {
    e.preventDefault();
    const f = e.target, m = root.querySelector('#lm');
    m.className = 'msg'; m.textContent = 'authenticating…';
    audio.ui('click');
    const r = await api.login(f.handle.value.trim(), f.password.value);
    if (!r || !r.ok) { m.textContent = (r && r.error) || 'Login failed.'; audio.ui('deny'); return; }
    audio.ui('ok');
    if (r.needArchetype) return screenArchetype(r.archetypes);
    const st = await api.state();
    if (st.ok) startGame(st.state); else screenLogin('Could not load your runner.');
  };
}

async function screenArchetype(cards) {
  if (!cards) {
    const w = await api.whoami();
    if (!w.authed) return screenLogin();
    const st = await api.state();
    cards = st.archetypes || (await api.login()).archetypes;
  }
  cards = cards || [];
  root.innerHTML = `
  <div class="screen"><div class="card">
    <div class="brand">HACKERS-MUD<small>CHARACTER CREATION</small></div>
    <h2>Who were you, before the Net?</h2>
    <p class="sub">Sets your starting stats and kit. You can raise anything later.</p>
    <div class="archs">${cards.map(c => `
      <button class="arch" data-n="${c.n}">
        <b>${c.name}</b>
        <div class="st">BOD ${c.stats.body} · REF ${c.stats.reflex} · INT ${c.stats.intel} · COOL ${c.stats.cool} · TECH ${c.stats.tech} · HP ${c.hp}</div>
        <div class="bl">${c.blurb}</div>
      </button>`).join('')}</div>
    <div class="msg" id="am"></div>
  </div></div>`;
  root.querySelectorAll('.arch').forEach(b => b.onclick = async () => {
    root.querySelectorAll('.arch').forEach(x => x.classList.remove('sel'));
    b.classList.add('sel'); audio.ui('click');
    root.querySelector('#am').textContent = 'jacking in…';
    const r = await api.archetype(b.dataset.n);
    if (r && r.ok && r.state) { audio.ui('ok'); startGame(r.state); }
    else root.querySelector('#am').textContent = (r && r.error) || 'Try again.';
  });
}

/* ---------- game ---------- */
function startGame(state) {
  ui = new UI();
  const stageHost = ui.mount(root);
  ui.soundIcon(sndOn);
  scene = new Scene();
  scene.mount(stageHost);
  scene.shake = n => scene._shake = Math.max(scene._shake, n);
  audio.shake = n => scene && scene.shake(n);

  ui.on('cmd', v => sendCmd(v));
  ui.on('act', v => handleAct(v));
  ui.on('modal', m => openModal(m));
  ui.on('sound', () => {
    sndOn = !sndOn; try { localStorage.setItem('hm_snd', sndOn ? '1' : '0'); } catch (_) {}
    audio.setOn(sndOn); ui.soundIcon(sndOn);
    if (sndOn && lastState) {
      audio.ambient(lastState.ambient);
      audio.music(lastState.player && lastState.player.state === 'fighting' ? 'battle' : 'idle');
    } else {
      audio.music(null);
    }
  });
  ui.on('music-toggle', on => { if (on && lastState) audio.music(lastState.player && lastState.player.state === 'fighting' ? 'battle' : 'idle'); else audio.music(null); });

  ui.on('inspect', mob => {
    api.cmd('consider ' + (mob.kw || mob.name)).then(r => {
      if (!r || !r.ok) return;
      ui.log(r.lines || []);
      if (r.state) applyState(r.state);
      ui.enemyCard(mob, r.lines || []);
    });
  });

  ui.on('sms', async d => {
    const r = await api.sms(d.to, d.body);
    if (r && r.ok) audio.ui('ok'); else audio.ui('deny');
    if (r && r.error) ui.log(['|09' + r.error]);
    const box = await api.inbox();
    ui.social(lastState, (box && box.inbox) || []);
  });

  ui.on('quit', async () => {
    clearInterval(poll);
    try { await api.logout(); } catch (_) {}
    audio.music(null); audio.ambientStop();
    scene && scene.destroy();
    root.innerHTML = `
    <div class="screen"><div class="card">
      <div class="brand">HACKERS-MUD<small>DISCONNECTED</small></div>
      <h2>Progress saved</h2>
      <p class="sub">Your runner — level, stats, gear, and location — is safe in Night City. Same character next time.</p>
      <div class="row"><button class="btn pri" id="rein">Jack back in</button></div>
    </div></div>`;
    root.querySelector('#rein').onclick = () => location.reload();
  });

  scene.on('exit', ex => sendCmd(ex.locked && ex.hackable ? 'hack ' + (ex.keyword || 'door') : ex.dir));
  scene.on('portal', exits => ui.portalChoice(exits));
  scene.on('interact', d => {
    if (d.type === 'item') return sendCmd('get ' + d.item.kw);
    if (d.type === 'player') {
      const p = d.player;
      return ui.toast(p.name + (p.title ? ' — ' + p.title : '') + '  ·  L' + (p.level || '?') + ' ' + (p.archetype || ''));
    }
    const m = d.mob;
    if (d.hostile) return sendCmd('kill ' + m.kw);
    if (m.shop) return openShop(m.kw);
    if (m.trainer) return sendCmd('train');
    if (m.ripperdoc) return sendCmd('uninstall');
    return sendCmd('talk ' + m.kw);
  });
  scene.on('bump-wall', () => audio.sfx('miss'));

  applyState(state, true);
  audio.music(state.player && state.player.state === 'fighting' ? 'battle' : 'idle');
  clearInterval(poll);
  poll = setInterval(refresh, 6000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}

let lastState = null;

function applyState(state, first, combat) {
  if (!state || !state.ok) return;
  const prev = lastState;
  lastState = state;
  ui.render(state);
  scene.setRoom(state.room, state.player);
  audio.ambient(state.ambient);
  if (sndOn) {
    const fighting = state.player && state.player.state === 'fighting';
    if (!prev || !prev.player || (prev.player.state === 'fighting') !== fighting)
      audio.music(fighting ? 'battle' : 'idle');
  }
  if (prev && prev.player) {
    const dh = state.player.hp - prev.player.hp;
    if (!combat) {   // playBattle shows per-hit numbers itself
      if (dh < 0) { scene.float(dh + '', '#ff3b57'); scene.shake(Math.min(14, -dh)); }
      else if (dh > 6) scene.float('+' + dh, '#3ce88b');
    }
    if (state.player.level > prev.player.level) { ui.toast('LEVEL ' + state.player.level); scene.pulse('levelup'); }
  }
  if (first) ui.log(state.log.slice(-40));
}

function parseCombat(lines) {
  const ev = [];
  for (const l of lines) {
    const p = l.replace(/\|\d\d/g, '');
    let m;
    if ((m = p.match(/^You (strike|shoot|punch|slam) .* for (\d+) damage(.*)$/)))
      ev.push({ src: 'you', kind: m[1] === 'shoot' ? 'gun' : (m[1] === 'punch' ? 'swing' : 'blade'), dmg: +m[2], crit: /CRIT/i.test(m[3]) });
    else if (/^You (strike|shoot|punch) at .* and miss/.test(p))
      ev.push({ src: 'you', miss: true, kind: /shoot/.test(p) ? 'gun' : 'blade' });
    else if ((m = p.match(/(?:hits|ambushes|attacks) you for (\d+)/)))
      ev.push({ src: 'mob', dmg: +m[1] });
    else if (/lunges and misses|takes a swing at you and misses/.test(p))
      ev.push({ src: 'mob', miss: true });
    else if (/is dropped\. It stops twitching|flatline|breaks and runs/.test(p))
      { if (ev.length) ev[ev.length - 1].killed = true; }
  }
  return ev;
}

async function sendCmd(cmd) {
  if (!cmd || inflight) return;
  inflight = true;
  // remember the likely combat target before state refreshes
  let targetId = null;
  const kw = /^(k|kill|attack|hit|fight)\b\s*(.*)$/i.exec(cmd);
  if (kw && lastState) {
    const hits = lastState.room.mobs.filter(x => !kw[2] || (x.kw && x.kw.includes(kw[2].trim().split(' ')[0])) || x.name.toLowerCase().includes(kw[2].trim()));
    targetId = (hits.find(x => x.hostile) || hits[0] || lastState.room.mobs.find(x => x.state === 'fighting') || {}).id || null;
  } else if (lastState && lastState.player.state === 'fighting') {
    targetId = (lastState.room.mobs.find(x => x.state === 'fighting') || {}).id || null;
  }
  const r = await api.cmd(cmd);
  inflight = false;
  if (r && r.stale) return relogin('Session expired. Sign in again.');
  if (r && r.error === 'auth') return relogin();
  if (r && r.error === 'nochar') return screenArchetype(r.archetypes);
  if (!r || !r.ok) { ui.log(['|09' + ((r && r.error) || 'CARRIER LOST')]); return; }
  ui.log(r.lines || []);
  (r.sfx || []).forEach((n, i) => setTimeout(() => audio.sfx(n), i * 80));
  const events = parseCombat(r.lines || []);
  applyState(r.state, false, events.length > 0);
  if (events.length && scene) scene.playBattle(targetId, events);
  // pop the loot screen when a fight just ended and there's something to grab
  const killed = events.some(e => e.killed) || /drops|clatters out|spills onto the|hits the ground/i.test((r.lines || []).join(' '));
  if (killed && /^(k|kill|attack|hit|fight)\b/i.test(cmd) && r.state && r.state.room && (r.state.room.items || []).length) {
    setTimeout(() => ui.loot(r.state), 420);
  }
}

async function refresh() {
  if (inflight || document.hidden) return;
  const st = await api.state();
  if (st && st.ok) {
    // surface any new tick lines (world events, aggro, MaxTac)
    const prevLen = (lastState && lastState.log.length) || 0;
    if (st.state.log.length > prevLen) ui.log(st.state.log.slice(prevLen - st.state.log.length));
    applyState(st.state);
  } else if (st && (st.error === 'auth' || st.stale)) {
    relogin();
  }
}

function handleAct(v) {
  if (v.startsWith('@shop:')) return openShop(v.slice(6));
  if (v.startsWith('@train:')) return sendCmd('train');
  if (v.startsWith('@ripper:')) return sendCmd('uninstall');
  sendCmd(v);
}

async function openModal(m) {
  audio.ui('open');
  if (m === 'inv') ui.inventory(lastState);
  else if (m === 'gear') ui.gear(lastState);
  else if (m === 'sheet') ui.sheet(lastState);
  else if (m === 'map') ui.mapModal(lastState);
  else if (m === 'help') ui.helpModal();
  else if (m === 'loot') ui.loot(lastState);
  else if (m === 'social') {
    ui.social(lastState, []);
    const box = await api.inbox();
    ui.social(lastState, (box && box.inbox) || []);
  }
}

async function openShop(kw) {
  const r = await api.cmd('list');
  if (!r || !r.ok) return;
  applyState(r.state);
  const lines = r.lines || [];
  const items = [];
  let name = 'Shop';
  for (const l of lines) {
    const plain = l.replace(/\|\d\d/g, '');
    const mm = plain.match(/^\s{2}(.+?)\s{2,}(\w+)\s+¥([\d,]+)(?:\s*\(x(\d+)\))?\s*$/);
    if (mm) items.push({ name: mm[1].trim(), type: mm[2], price: mm[3], qty: mm[4] || '' });
    else if (/^[A-Z]/.test(plain.trim()) && !name.startsWith('The') && items.length === 0 && plain.trim().length < 40 && !plain.includes('ITEM')) name = plain.trim();
  }
  const bare = s => s.replace(/^(a|an|the)\s+/i, '');
  const esc = s => String(s).replace(/[<>&]/g, '');

  // what this keeper will take off your hands
  const shop = (lastState && lastState.room && lastState.room.shop) || {};
  const buys = shop.buys || [];
  const markdown = shop.markdown || 0.4;
  const takesAll = buys.includes('*');
  const noTrade = it => /quest|notrade|nodrop/.test(it.flags || '');
  const sellable = ((lastState && lastState.inventory) || []).filter(it =>
    (takesAll || buys.includes(it.type)) && !noTrade(it));

  const buyHtml = items.length ? items.map(it => `<div class="shoprow">
      <span style="color:var(--cyan)">▸</span>
      <span class="nm">${esc(it.name)}<br><span style="font-size:10px;color:var(--dim)">${it.type}${it.qty ? ' · x' + it.qty : ''}</span></span>
      <span class="pr">¥${it.price}</span>
      <button class="btn sm pri" data-buy="${bare(it.name).replace(/"/g, '')}">Buy</button></div>`).join('')
    : '<p style="color:var(--dim)">Nothing in stock.</p>';

  const sellHtml = sellable.length ? sellable.map(it => {
    const price = Math.max(1, Math.floor((it.value || 0) * markdown));
    return `<div class="shoprow">
      <span style="color:var(--grn)">▾</span>
      <span class="nm">${esc(it.name)}${it.qty > 1 ? ' <span style="color:var(--dim)">x' + it.qty + '</span>' : ''}<br><span style="font-size:10px;color:var(--dim)">${it.type}</span></span>
      <span class="pr">~¥${price.toLocaleString()}</span>
      <button class="btn sm" data-sell="${esc(it.kw)}">Sell</button></div>`;
  }).join('')
    : `<p style="color:var(--dim)">Nothing in your pack this keeper wants${takesAll ? '.' : ' (they take: ' + (buys.join(', ') || '—') + ').'}</p>`;

  const html = `<p style="color:var(--mut);margin-top:0">Buy from stock, or sell what they deal in. Prices shift with your gear's condition.</p>
    <div class="sect">In stock</div>${buyHtml}
    <div class="sect" style="margin-top:14px">They'll buy from you</div>${sellHtml}`;

  const rootEl = ui._modal(name, html);
  rootEl.querySelectorAll('[data-buy]').forEach(b => b.onclick = async () => {
    await sendCmd('buy ' + b.dataset.buy);
    openShop(kw);
  });
  rootEl.querySelectorAll('[data-sell]').forEach(b => b.onclick = async () => {
    await sendCmd('sell ' + b.dataset.sell);
    openShop(kw);
  });
}

function relogin(msg) {
  clearInterval(poll);
  audio.music(null);
  audio.ambientStop();
  scene && scene.destroy();
  screenLogin(msg || 'Please sign in.');
}
