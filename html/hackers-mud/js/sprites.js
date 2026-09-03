/* sprites.js - procedural pixel-art draw kit (no external assets).
   Structured so a real CC0 atlas could replace the draw fns later:
   swap drawActor/drawItem/drawTile with atlas blits keyed by the same names. */

/* ---- palettes ---- */
export const THEME = {
  street:  { floor: '#14161f', floor2: '#191c28', grout: '#0b0c13', wall: '#0e0f17', edge: '#ff2d55', glow: '#66e0ff', wet: '#1b2f3a' },
  corpo:   { floor: '#20222c', floor2: '#272a37', grout: '#14151d', wall: '#2b2e3c', edge: '#66e0ff', glow: '#a8b7ff', wet: '#2a2d3b' },
  ruin:    { floor: '#191712', floor2: '#201d17', grout: '#0d0b08', wall: '#14120d', edge: '#ff9b45', glow: '#8a7', wet: '#20242a' },
  tunnel:  { floor: '#0f1114', floor2: '#13161a', grout: '#070809', wall: '#0b0d10', edge: '#3ce88b', glow: '#3ce88b', wet: '#12202a' },
  arcade:  { floor: '#1c1230', floor2: '#241640', grout: '#120a22', wall: '#1a0f2c', edge: '#ff2d55', glow: '#b98cff', wet: '#2a1840' },
  desert:  { floor: '#241d13', floor2: '#2c241a', grout: '#171208', wall: '#1c160e', edge: '#ffcf4a', glow: '#ff9b45', wet: '#232a20' },
  grid:    { floor: '#080b12', floor2: '#0a0f18', grout: '#05070c', wall: '#0a0d16', edge: '#66e0ff', glow: '#66e0ff', wet: '#0c1420' },
};

/* deterministic PRNG */
function rng(seed) { let s = seed >>> 0 || 1; return () => (s = (s * 1664525 + 1013904223) >>> 0) / 4294967296; }

/* ---- tiles ---- */
export function drawTile(ctx, px, py, T, themeKey, seed, isWall) {
  const p = THEME[themeKey] || THEME.street;
  const r = rng(seed * 131 + (isWall ? 7 : 3));
  ctx.fillStyle = isWall ? p.wall : (r() < 0.5 ? p.floor : p.floor2);
  ctx.fillRect(px, py, T, T);
  // grout / seams
  ctx.strokeStyle = p.grout; ctx.lineWidth = 1;
  ctx.strokeRect(px + 0.5, py + 0.5, T - 1, T - 1);
  if (isWall) {
    ctx.fillStyle = 'rgba(255,255,255,.03)'; ctx.fillRect(px, py, T, 2);
    if (r() < 0.25) { ctx.fillStyle = p.edge + '22'; ctx.fillRect(px + 2, py + T * 0.3, T - 4, 2); }
    return;
  }
  // speckle / grime
  ctx.fillStyle = 'rgba(0,0,0,.25)';
  for (let i = 0; i < 3; i++) ctx.fillRect(px + (r() * T) | 0, py + (r() * T) | 0, 1, 1);
  if (themeKey === 'grid') {
    ctx.strokeStyle = p.glow + '22';
    ctx.strokeRect(px + 0.5, py + 0.5, T - 1, T - 1);
  } else if (r() < 0.16) {
    // neon puddle / reflection
    ctx.fillStyle = p.wet + 'cc';
    ctx.fillRect(px + (T * 0.2) | 0, py + (T * 0.55) | 0, (T * 0.55) | 0, (T * 0.3) | 0);
    ctx.fillStyle = p.glow + '18';
    ctx.fillRect(px + (T * 0.25) | 0, py + (T * 0.58) | 0, (T * 0.4) | 0, 2);
  }
}

export function drawProp(ctx, px, py, T, themeKey, kind, seed) {
  const p = THEME[themeKey] || THEME.street;
  ctx.save(); ctx.translate(px, py);
  const u = T / 16;
  const box = (x, y, w, h, c) => { ctx.fillStyle = c; ctx.fillRect(x * u, y * u, w * u, h * u); };
  if (kind === 'crate') { box(3, 7, 10, 8, '#2a241a'); box(3, 7, 10, 2, '#3a3222'); box(7, 7, 2, 8, '#1c180f'); }
  else if (kind === 'barrel') { box(4, 5, 8, 10, '#20242c'); box(4, 5, 8, 2, '#333a46'); box(4, 9, 8, 1, p.edge + '66'); }
  else if (kind === 'sign') { box(7, 2, 2, 9, '#111'); box(2, 1, 12, 5, p.wall); ctx.fillStyle = p.edge; ctx.fillRect(3 * u, 2 * u, 10 * u, 1); ctx.fillStyle = p.glow; ctx.fillRect(4 * u, 3.5 * u, 6 * u, 1); }
  else if (kind === 'trash') { box(4, 8, 9, 7, '#161616'); box(4, 8, 9, 1.5, '#242424'); box(6, 5, 2, 3, '#333'); }
  else if (kind === 'plant') { box(6, 10, 5, 5, '#1c150c'); ctx.fillStyle = '#2f5a2a'; for (let i = 0; i < 5; i++) ctx.fillRect((5 + i) * u, (5 + (i % 2) * 2) * u, 1.4 * u, 5 * u); }
  else if (kind === 'terminal') { box(4, 4, 8, 11, '#0c0f14'); ctx.fillStyle = p.glow; ctx.fillRect(5 * u, 5 * u, 6 * u, 4 * u); ctx.fillStyle = '#000'; ctx.fillRect(6 * u, 6 * u, 4 * u, 1); ctx.fillRect(6 * u, 7.5 * u, 3 * u, 1); }
  else if (kind === 'pipe') { box(0, 6, 16, 3, '#1a1d22'); box(0, 6, 16, 1, '#2a2f36'); }
  else if (kind === 'rubble') { box(3, 11, 4, 4, '#26221a'); box(8, 12, 5, 3, '#2c281f'); box(6, 10, 3, 3, '#222'); }
  else if (kind === 'car') { box(1, 7, 14, 6, '#1a1a22'); box(3, 4, 10, 4, '#20202a'); ctx.fillStyle = p.edge + '55'; ctx.fillRect(1 * u, 8 * u, 14 * u, 1); box(3, 12, 3, 3, '#000'); box(10, 12, 3, 3, '#000'); }
  else if (kind === 'neon') { ctx.fillStyle = p.edge; ctx.shadowColor = p.edge; ctx.shadowBlur = 8 * u; ctx.fillRect(2 * u, 3 * u, 12 * u, 1.5 * u); ctx.fillStyle = p.glow; ctx.fillRect(2 * u, 6 * u, 8 * u, 1.5 * u); ctx.shadowBlur = 0; }
  ctx.restore();
}

/* ---- items ---- */
export function drawItemGlyph(ctx, cx, cy, size, icon) {
  const u = size / 16;
  ctx.save(); ctx.translate(cx - size / 2, cy - size / 2);
  const b = (x, y, w, h, c) => { ctx.fillStyle = c; ctx.fillRect(x * u, y * u, w * u, h * u); };
  const g = ({ street: '#66e0ff' }[icon]) || '#ffcf4a';
  switch (icon) {
    case 'gun': b(2, 7, 9, 3, '#3a3f4a'); b(9, 6, 3, 2, '#4a505c'); b(3, 9, 2, 4, '#2a2e36'); b(2, 7, 9, 1, '#5a616f'); break;
    case 'blade': b(7, 2, 2, 9, '#c9d3e6'); b(6, 2, 4, 1, '#eef3ff'); b(6, 11, 4, 2, '#5a2a1a'); b(5, 12, 6, 1, '#3a1c12'); break;
    case 'armor': b(4, 3, 8, 3, '#2f3a4a'); b(3, 5, 10, 7, '#26303e'); b(3, 5, 10, 1, '#3f4c60'); b(6, 6, 4, 4, '#1c242f'); break;
    case 'chip': b(3, 4, 10, 8, '#123'); b(4, 5, 8, 6, '#1c3a2c'); ctx.fillStyle = '#3ce88b'; for (let i = 0; i < 3; i++) { ctx.fillRect(2 * u, (5 + i * 2) * u, 2 * u, 1); ctx.fillRect(12 * u, (5 + i * 2) * u, 2 * u, 1); } break;
    case 'deck': b(2, 4, 12, 8, '#14161d'); b(3, 5, 10, 4, '#0e1b24'); ctx.fillStyle = '#66e0ff'; ctx.fillRect(4 * u, 6 * u, 8 * u, 1); b(4, 10, 3, 1, '#333'); b(9, 10, 3, 1, '#333'); break;
    case 'food': b(4, 5, 8, 7, '#7a4a2a'); b(4, 5, 8, 2, '#9a6238'); b(5, 8, 6, 1, '#c98'); break;
    case 'drink': b(6, 3, 4, 10, '#1b3a44'); b(6, 3, 4, 2, '#2a5560'); b(6, 6, 4, 3, '#3ce88b'); break;
    case 'stim': b(7, 2, 2, 8, '#cfd6e6'); b(6, 3, 4, 2, '#e6ebf5'); b(7, 10, 2, 3, '#8a2a3a'); b(6, 12, 4, 1, '#aa3548'); break;
    case 'gadget': b(4, 4, 8, 8, '#242a34'); b(4, 4, 8, 1, '#3a424f'); ctx.fillStyle = '#ffcf4a'; ctx.fillRect(6 * u, 6 * u, 4 * u, 4 * u); break;
    case 'light': b(6, 3, 4, 4, '#ffe08a'); ctx.fillStyle = '#ffcf4a'; ctx.shadowColor = '#ffcf4a'; ctx.shadowBlur = 6 * u; ctx.fillRect(6 * u, 3 * u, 4 * u, 4 * u); ctx.shadowBlur = 0; b(6, 7, 4, 6, '#2a2a2a'); break;
    case 'bag': b(3, 5, 10, 8, '#3a2f22'); b(5, 3, 6, 3, '#2a2016'); b(3, 5, 10, 1, '#4a3d2c'); break;
    case 'eddies': ctx.fillStyle = '#ffcf4a'; for (let i = 0; i < 3; i++) ctx.fillRect((4 + i) * u, (10 - i) * u, 8 * u, 2 * u); ctx.fillStyle = '#b98a1e'; ctx.fillRect(5 * u, 9 * u, 6 * u, 1); break;
    case 'shard': b(6, 2, 4, 12, '#2a1a3a'); b(6, 2, 4, 12, '#b98cff44'); ctx.fillStyle = '#b98cff'; ctx.fillRect(7 * u, 3 * u, 2 * u, 9 * u); break;
    case 'scrap': b(3, 8, 5, 4, '#3a3020'); b(8, 6, 5, 6, '#2f2a1c'); b(6, 10, 4, 3, '#222'); break;
    default: b(4, 6, 8, 6, '#2a2f38'); b(4, 6, 8, 1, '#3d4450'); ctx.fillStyle = '#777'; ctx.fillRect(6 * u, 8 * u, 4 * u, 2 * u);
  }
  ctx.restore();
}

export function drawGroundItem(ctx, cx, cy, T, icon, tt) {
  const bob = Math.sin(tt / 260) * 2;
  ctx.save();
  ctx.globalAlpha = 0.35;
  ctx.fillStyle = '#66e0ff';
  ctx.beginPath(); ctx.ellipse(cx, cy + 8, 9, 3, 0, 0, 7); ctx.fill();
  ctx.globalAlpha = 1;
  ctx.shadowColor = '#66e0ff'; ctx.shadowBlur = 10;
  drawItemGlyph(ctx, cx, cy + bob, T * 0.62, icon);
  ctx.restore();
}

/* ---- actors ---- */
const BODY = {
  netrunner: { skin: '#c98d63', hair: '#1a1a22', jacket: '#1d2b3a', pants: '#14161d', acc: '#66e0ff' },
  solo:      { skin: '#b97a4e', hair: '#20140c', jacket: '#3a2018', pants: '#22201a', acc: '#ff2d55' },
  techie:    { skin: '#caa07a', hair: '#3a2a18', jacket: '#243a24', pants: '#1c2018', acc: '#ffcf4a' },
  cop:       { skin: '#b98a66', hair: '#111', jacket: '#141824', pants: '#0e1018', acc: '#3a6bff' },
  maxtac:    { skin: '#333', hair: '#111', jacket: '#0c0c10', pants: '#0a0a0e', acc: '#ff2d55', bulk: 1 },
  ganger:    { skin: '#c98d63', hair: '#d4af37', jacket: '#3a2f12', pants: '#1a1a1a', acc: '#ffcf4a' },
  scav:      { skin: '#9a7a5a', hair: '#2a2a2a', jacket: '#2a2620', pants: '#201c16', acc: '#8a2a2a' },
  raffen:    { skin: '#a97a52', hair: '#1a1a1a', jacket: '#2b241a', pants: '#231d14', acc: '#ff9b45', bulk: 1 },
  corpo:     { skin: '#caa07a', hair: '#1a1a22', jacket: '#20222c', pants: '#1a1c24', acc: '#a8b7ff' },
  nomad:     { skin: '#b98a5e', hair: '#3a2a18', jacket: '#3a2f22', pants: '#2a2418', acc: '#ffcf4a' },
  fixer:     { skin: '#c98d63', hair: '#20140c', jacket: '#1a1a22', pants: '#141418', acc: '#66e0ff' },
  punk:      { skin: '#c98d63', hair: '#ff2d55', jacket: '#241a24', pants: '#1a1a1a', acc: '#ff2d55' },
  civ:       { skin: '#c98d63', hair: '#2a2016', jacket: '#2a2e38', pants: '#242832', acc: '#8b90b2' },
  boss:      { skin: '#a86', hair: '#111', jacket: '#241018', pants: '#180c10', acc: '#ffcf4a', bulk: 1.3 },
};

export function drawActor(ctx, cx, cy, T, kind, opts = {}) {
  const tt = opts.tt || 0;
  const facing = opts.facing || 's';
  if (kind === 'rat' || kind === 'cat' || kind === 'dog') return drawCritter(ctx, cx, cy, T, kind, opts);
  if (kind === 'drone' || kind === 'ghost' || kind === 'ai' || kind === 'construct') return drawFloater(ctx, cx, cy, T, kind, opts);
  if (kind === 'ghoul') return drawGhoul(ctx, cx, cy, T, opts);

  const b = BODY[kind] || BODY.civ;
  const bulk = b.bulk || 1;
  const u = (T / 16) * 0.9;
  const walk = opts.walk ? Math.sin(tt / 90) : 0;
  ctx.save();
  ctx.translate(cx, cy);
  // shadow
  ctx.globalAlpha = .35; ctx.fillStyle = '#000';
  ctx.beginPath(); ctx.ellipse(0, 7 * u, 5 * u * bulk, 2 * u, 0, 0, 7); ctx.fill();
  ctx.globalAlpha = 1;
  if (opts.hurt) { ctx.globalAlpha = 0.6 + 0.4 * Math.sin(tt / 40); }
  const P = (x, y, w, h, c) => { ctx.fillStyle = c; ctx.fillRect(x * u - (w * u) / 2, y * u, w * u, h * u); };
  // legs
  P(-1.6 * bulk + walk, 2, 1.8 * bulk, 5, b.pants);
  P(1.6 * bulk - walk, 2, 1.8 * bulk, 5, b.pants);
  // torso / jacket
  P(0, -3.5, 6.2 * bulk, 6, b.jacket);
  P(0, -3.5, 6.2 * bulk, 1.4, 'rgba(255,255,255,.06)');
  // accent stripe
  ctx.fillStyle = b.acc; ctx.fillRect(-0.6 * u, -3.5 * u, 1.2 * u, 6 * u);
  // arms
  P(-4 * bulk - walk * .5, -3, 1.6 * bulk, 5.5, b.jacket);
  P(4 * bulk + walk * .5, -3, 1.6 * bulk, 5.5, b.jacket);
  // head
  P(0, -8.5, 3.6, 3.6, b.skin);
  P(0, -9.2, 4, 1.8, b.hair);
  if (facing === 'n') { /* back of head */ P(0, -8.5, 3.6, 3.4, b.hair); }
  else { ctx.fillStyle = '#0a0a0a'; ctx.fillRect(-1.4 * u, -7.4 * u, 0.9 * u, 0.9 * u); ctx.fillRect(0.6 * u, -7.4 * u, 0.9 * u, 0.9 * u); }
  // boss / hunter aura
  if (opts.boss) {
    ctx.strokeStyle = b.acc; ctx.globalAlpha = .5 + .3 * Math.sin(tt / 200); ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.arc(0, -2 * u, 9 * u, 0, 7); ctx.stroke(); ctx.globalAlpha = 1;
  }
  ctx.restore();
}

function drawCritter(ctx, cx, cy, T, kind, opts) {
  const u = (T / 16) * 0.9, tt = opts.tt || 0;
  const col = kind === 'rat' ? '#5a5250' : kind === 'cat' ? '#3a3a42' : '#5a4a36';
  ctx.save(); ctx.translate(cx, cy);
  ctx.globalAlpha = .3; ctx.fillStyle = '#000'; ctx.beginPath(); ctx.ellipse(0, 5 * u, 5 * u, 2 * u, 0, 0, 7); ctx.fill(); ctx.globalAlpha = 1;
  if (opts.hurt) ctx.globalAlpha = 0.6 + 0.4 * Math.sin(tt / 40);
  ctx.fillStyle = col;
  ctx.fillRect(-4 * u, 0, 8 * u, 4 * u);              // body
  ctx.fillRect(3 * u, -2 * u, 3 * u, 3 * u);          // head
  ctx.fillRect(-6 * u + Math.sin(tt / 120) * u, 1 * u, 3 * u, 1 * u); // tail
  ctx.fillStyle = '#ff2d55'; ctx.fillRect(4.5 * u, -1.4 * u, 0.8 * u, 0.8 * u); // eye
  if (kind !== 'rat') { ctx.fillStyle = col; ctx.fillRect(3.2 * u, -3.4 * u, 1 * u, 1.4 * u); ctx.fillRect(5 * u, -3.4 * u, 1 * u, 1.4 * u); } // ears
  ctx.restore();
}

function drawFloater(ctx, cx, cy, T, kind, opts) {
  const u = (T / 16) * 0.9, tt = opts.tt || 0;
  const y = Math.sin(tt / 300) * 3;
  ctx.save(); ctx.translate(cx, cy + y);
  ctx.globalAlpha = .25; ctx.fillStyle = '#000'; ctx.beginPath(); ctx.ellipse(0, 8 * u - y, 5 * u, 2 * u, 0, 0, 7); ctx.fill(); ctx.globalAlpha = 1;
  if (kind === 'drone' || kind === 'construct') {
    ctx.fillStyle = kind === 'drone' ? '#2a2e36' : '#161a26';
    ctx.fillRect(-4 * u, -3 * u, 8 * u, 6 * u);
    ctx.fillStyle = '#66e0ff'; ctx.fillRect(-2 * u, -1 * u, 4 * u, 2 * u);
    ctx.strokeStyle = '#3a424f'; ctx.beginPath(); ctx.moveTo(-4 * u, -3 * u); ctx.lineTo(-7 * u, -5 * u); ctx.moveTo(4 * u, -3 * u); ctx.lineTo(7 * u, -5 * u); ctx.stroke();
  } else { // ghost / ai
    ctx.globalAlpha = .55;
    ctx.fillStyle = kind === 'ai' ? '#b98cff' : '#9fd6ff';
    ctx.fillRect(-3.5 * u, -6 * u, 7 * u, 9 * u);
    for (let i = 0; i < 4; i++) ctx.fillRect((-3.5 + i * 2) * u, (3 + Math.sin(tt / 100 + i) * 1.5) * u, 1.4 * u, 2 * u);
    ctx.globalAlpha = 1; ctx.fillStyle = '#fff'; ctx.fillRect(-1.6 * u, -3.4 * u, 0.9 * u, 0.9 * u); ctx.fillRect(0.7 * u, -3.4 * u, 0.9 * u, 0.9 * u);
  }
  if (opts.boss) { ctx.strokeStyle = '#ffcf4a'; ctx.globalAlpha = .5; ctx.beginPath(); ctx.arc(0, 0, 10 * u, 0, 7); ctx.stroke(); }
  ctx.restore();
}

function drawGhoul(ctx, cx, cy, T, opts) {
  const u = (T / 16) * 0.9, tt = opts.tt || 0;
  ctx.save(); ctx.translate(cx, cy);
  ctx.globalAlpha = .3; ctx.fillStyle = '#000'; ctx.beginPath(); ctx.ellipse(0, 7 * u, 5 * u, 2 * u, 0, 0, 7); ctx.fill(); ctx.globalAlpha = 1;
  if (opts.hurt) ctx.globalAlpha = 0.6 + 0.4 * Math.sin(tt / 40);
  ctx.fillStyle = '#5a5b52';
  ctx.fillRect(-2 * u, 1 * u, 2 * u, 6 * u); ctx.fillRect(1 * u, 1 * u, 2 * u, 6 * u);
  ctx.fillRect(-3.5 * u, -4 * u, 7 * u, 6 * u);
  ctx.fillRect(-5 * u, -3 * u, 1.6 * u, 6 * u); ctx.fillRect(4 * u, -3 * u, 1.6 * u, 6 * u);
  ctx.fillStyle = '#6b6c60'; ctx.fillRect(-2 * u, -8 * u, 4 * u, 4 * u);
  ctx.fillStyle = '#ff9b45'; ctx.fillRect(-1.4 * u, -6.6 * u, 0.9 * u, 0.9 * u); ctx.fillRect(0.6 * u, -6.6 * u, 0.9 * u, 0.9 * u);
  ctx.restore();
}

/* an offscreen canvas for a UI icon (inventory grid etc.) */
export function iconCanvas(icon, size = 44) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  drawItemGlyph(c.getContext('2d'), size / 2, size / 2, size * 0.9, icon);
  return c;
}
export function actorCanvas(kind, size = 30) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  drawActor(c.getContext('2d'), size / 2, size * 0.62, size * 1.15, kind, { tt: 0 });
  return c;
}
