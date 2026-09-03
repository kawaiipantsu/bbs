/* sprites.js - draw kit. Tiles + props come from the bundled Kenney CC0
   sheets (atlas.js) with a procedural fallback; actors and item icons are
   drawn procedurally in a cohesive cyberpunk style. */
import { city, CITY, blit, atlasReady } from './atlas.js';

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

/* ---- tiles (Kenney city sheet, per theme + role) ---- */
/* roles: floor road roadline sidewalk grass wall building door */
const TILEMAP = {
  street:  { floor: 'road', road: 'road', roadline: 'road_dash', sidewalk: 'sidewalk', grass: 'grass', wall: 'wall_glass', building: 'roof_grey', door: 'door_glass' },
  corpo:   { floor: 'sidewalk_tan', road: 'road', roadline: 'road_yellow', sidewalk: 'sidewalk_tan', grass: 'grass', wall: 'wall_grey', building: 'roof_grey', door: 'door_glass' },
  ruin:    { floor: 'dirt', road: 'road', roadline: 'road_dash', sidewalk: 'sidewalk', grass: 'grass', wall: 'wall_brick', building: 'roof_red', door: 'door_wood' },
  tunnel:  { floor: 'dirt', road: 'dirt', roadline: 'dirt', sidewalk: 'sidewalk', grass: 'grass', wall: 'wall_grey', building: 'wall_grey', door: 'door_wood' },
  arcade:  { floor: 'sidewalk', road: 'road', roadline: 'road_dash', sidewalk: 'sidewalk', grass: 'grass', wall: 'wall_glasslit', building: 'roof_grey', door: 'door_arch' },
  desert:  { floor: 'dirt', road: 'road', roadline: 'road_dash', sidewalk: 'sidewalk_tan', grass: 'grass', wall: 'wall_tan', building: 'roof_red', door: 'door_wood' },
  grid:    { floor: 'road', road: 'road', roadline: 'road_dash', sidewalk: 'sidewalk', grass: 'grass', wall: 'wall_glass', building: 'roof_grey', door: 'door_glass' },
};

export function drawTile(ctx, px, py, T, themeKey, seed, role) {
  if (role === true) role = 'wall';
  if (!role) role = 'floor';
  const p = THEME[themeKey] || THEME.street;
  const tm = TILEMAP[themeKey] || TILEMAP.street;
  const name = tm[role] || tm.floor;

  if (atlasReady() && city(name, ctx, px, py, T)) {
    // subtle theme tint + grime so different districts read differently
    const r = rng(seed * 131 + 5);
    if (role === 'floor' || role === 'road' || role === 'sidewalk') {
      ctx.fillStyle = 'rgba(0,0,0,.18)';
      for (let i = 0; i < 2; i++) ctx.fillRect(px + (r() * T) | 0, py + (r() * T) | 0, 1, 1);
      if (themeKey === 'grid') { ctx.strokeStyle = p.glow + '30'; ctx.strokeRect(px + 0.5, py + 0.5, T - 1, T - 1); }
      else if (themeKey === 'tunnel' || themeKey === 'ruin') { ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.fillRect(px, py, T, T); }
      else if (r() < 0.12) { ctx.fillStyle = p.glow + '14'; ctx.fillRect(px + 3, py + T * 0.6, T - 6, 2); }
    }
    if (role === 'wall' || role === 'building') { ctx.fillStyle = 'rgba(0,0,0,.15)'; ctx.fillRect(px, py, T, T); ctx.fillStyle = p.edge + '18'; ctx.fillRect(px, py + T - 2, T, 2); }
    return;
  }

  // ---- procedural fallback ----
  const r = rng(seed * 131 + (role === 'wall' ? 7 : 3));
  const isWall = role === 'wall' || role === 'building';
  ctx.fillStyle = isWall ? p.wall : (role === 'grass' ? '#1f3a1f' : (r() < 0.5 ? p.floor : p.floor2));
  ctx.fillRect(px, py, T, T);
  ctx.strokeStyle = p.grout; ctx.lineWidth = 1; ctx.strokeRect(px + 0.5, py + 0.5, T - 1, T - 1);
  if (isWall) { ctx.fillStyle = 'rgba(255,255,255,.03)'; ctx.fillRect(px, py, T, 2); return; }
  ctx.fillStyle = 'rgba(0,0,0,.25)';
  for (let i = 0; i < 3; i++) ctx.fillRect(px + (r() * T) | 0, py + (r() * T) | 0, 1, 1);
  if (role === 'road' || role === 'roadline') { ctx.fillStyle = '#0c0d12'; ctx.fillRect(px, py, T, T); if (role === 'roadline') { ctx.fillStyle = '#c9a94a'; ctx.fillRect(px + T / 2 - 1, py, 2, T); } }
}

/* prop kind -> Kenney city frame name (scale multiplier optional) */
const PROP_FRAME = {
  crate: 'barrier', barrel: 'dumpster_g', sign: 'neon_o', trash: 'dumpster_o',
  plant: 'bush', terminal: 'vending', pipe: null, rubble: 'cone', car: 'car_side',
  neon: 'neon_t', tree: 'tree', tree_a: 'tree_a', dumpster: 'dumpster_g',
  hydrant: 'pole', cone: 'cone', vending: 'vending', barrier: 'barrier',
  awning: 'awning_g', acbox: 'acbox', window: 'window', door: 'door_glass',
};

export function drawProp(ctx, px, py, T, themeKey, kind, seed) {
  const p = THEME[themeKey] || THEME.street;
  const frame = PROP_FRAME[kind];
  if (frame && atlasReady()) {
    ctx.save();
    ctx.shadowColor = /neon|vending|awning/.test(frame) ? p.glow : 'transparent';
    ctx.shadowBlur = /neon/.test(frame) ? 10 : 0;
    city(frame, ctx, px, py, T);
    ctx.restore();
    return;
  }
  // fallback
  ctx.save(); ctx.translate(px, py);
  const u = T / 16;
  const box = (x, y, w, h, c) => { ctx.fillStyle = c; ctx.fillRect(x * u, y * u, w * u, h * u); };
  if (kind === 'terminal' || kind === 'vending') { box(4, 3, 8, 12, '#0c0f14'); ctx.fillStyle = p.glow; ctx.fillRect(5 * u, 4 * u, 6 * u, 4 * u); }
  else if (kind === 'tree' || kind === 'plant' || kind === 'bush') { box(6, 9, 4, 6, '#3a2a18'); ctx.fillStyle = '#2f5a2a'; ctx.beginPath(); ctx.arc(8 * u, 6 * u, 5 * u, 0, 7); ctx.fill(); }
  else if (kind === 'car') { box(1, 5, 14, 8, '#242430'); box(3, 3, 10, 4, '#2c2c3a'); ctx.fillStyle = p.edge + '66'; ctx.fillRect(1 * u, 7 * u, 14 * u, 1); }
  else if (kind === 'pipe') { box(0, 6, 16, 3, '#1a1d22'); }
  else if (kind === 'stairs' || kind === 'stairsup' || kind === 'stairsdown') {
    const down = kind === 'stairsdown';
    box(2, 2, 12, 13, '#0d1017');                    // stairwell shaft
    for (let s = 0; s < 5; s++) {
      // down: treads fade bright->dark descending; up: dark->bright rising
      const lum = down ? 44 - s * 8 : 20 + s * 9;
      box(3, 2.6 + s * 2.4, 10, 1.9, `rgb(${lum},${lum + 5},${lum + 12})`);
    }
    ctx.fillStyle = p.glow + (down ? '40' : '88');   // tread nosings
    for (let s = 0; s < 5; s++) ctx.fillRect(3 * u, (2.6 + s * 2.4) * u, 10 * u, Math.max(1, u * 0.6));
    box(2, 2, 1.2, 13, '#05070c'); box(12.8, 2, 1.2, 13, '#05070c'); // rails
  }
  else { box(4, 6, 8, 9, '#20242c'); box(4, 6, 8, 2, '#333a46'); }
  ctx.restore();
}

/* ---- items: a distinct pixel glyph per icon key (Bbs\Mud\Icons) ---- */
const ICON_ALIAS = {
  // weapons
  gun: 'pistol', blade: 'knife', sword: 'knife', tanto: 'knife',
  revolver: 'pistol', zipgun: 'pistol', flare: 'pistol', techpistol: 'pistol',
  sniper: 'rifle', shotgun: 'rifle', carbine: 'rifle',
  crowbar: 'wrench', maul: 'sledge', baton: 'wrench', fist: 'wrench', axe: 'machete', cleaver: 'machete', saw: 'machete', cutter: 'machete', whip: 'knife',
  // cyber
  jack: 'chip', lens: 'optic', gorillarm: 'servo', heart: 'chip', pump: 'chip', spine: 'chip', weave: 'chip', launcher: 'chip', mantis: 'knife',
  // armour
  armor: 'jacket', poncho: 'coat', longcoat: 'coat', suit: 'jacket', plate: 'vest', bandolier: 'vest',
  hat: 'helmet', crown: 'helmet', monocle: 'shades', gasmask: 'goggles', scarf: 'goggles',
  belt: 'harness', shield: 'vest',
  // consumables
  food: 'rationbar', drink: 'can', stim: 'syringe', drugvial: 'syringe', cartridge: 'syringe',
  skewer: 'rationbar', rice: 'bento', bun: 'rationbar', crisps: 'rationbar', jerky: 'rationbar',
  energydrink: 'can', smoothie: 'bottle', coffee: 'bottle', waterbulb: 'bottle', flask: 'bottle',
  medkit: 'inhaler',
  // gadgets
  gadget: 'toolkit', decryptor: 'lockpick', cloner: 'keycard', spike: 'lockpick',
  boltcutter: 'wrench', icebreaker: 'chip', tracer: 'scanner', jammer: 'scanner',
  // light / containers
  light: 'flashlight', glowstick: 'flashlight', headlamp: 'flashlight', lantern: 'flashlight',
  briefcase: 'lockbox', bag: 'bag',
  // junk / lore
  file: 'book', map: 'book', tape: 'disc', datafrag: 'shard', locket: 'chip',
  credchip: 'keycard', cashbundle: 'eddies', tube: 'scrap', trophystring: 'tail',
  circuit: 'servo', fibre: 'cable', solarcell: 'servo', marker: 'syringe', tickets: 'book',
  pigeon: 'junk', bodybag: 'bag',
};

/* per-item visual identity: derive a stable variant from a seed string
   (item name or vnum) so no two catalogue entries render the same. */
function _iseed(s) {
  s = String(s == null ? '' : s);
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); }
  return h >>> 0;
}
function _variant(seed) {
  if (seed === '' || seed == null) return null;
  const h = _iseed(seed);
  const MK = ['#ff2d55', '#ffcf4a', '#3ce88b', '#66e0ff', '#b98cff', '#f2f4ff', '#ff9b45'];
  return {
    hue: h % 360,
    sat: 0.7 + ((h >>> 8) % 70) / 100,
    bri: 0.86 + ((h >>> 15) % 32) / 100,
    rot: (((h >>> 20) & 15) - 7.5) * 0.010,
    mk: (h >>> 12) % 7,
    mkc: MK[(h >>> 25) % MK.length],
    plate: h & 1,
  };
}
function _mark(ctx, ox, oy, size, V) {
  const u = size / 16;
  ctx.save(); ctx.translate(ox, oy);
  ctx.fillStyle = V.mkc; ctx.strokeStyle = V.mkc; ctx.globalAlpha = 0.9;
  switch (V.mk) {
    case 0: ctx.fillRect(0.6 * u, 0.6 * u, 2.2 * u, 2.2 * u); break;
    case 1: ctx.lineWidth = 1.4 * u; ctx.beginPath(); ctx.moveTo(0, 4 * u); ctx.lineTo(4 * u, 0); ctx.stroke(); break;
    case 2: for (let i = 0; i < 3; i++) ctx.fillRect(11.5 * u, (2 + i * 2) * u, 3 * u, 1); break;
    case 3: ctx.beginPath(); ctx.arc(13 * u, 13 * u, 1.8 * u, 0, 7); ctx.fill(); break;
    case 4: ctx.globalAlpha = 0.8; ctx.fillRect(0, 13.3 * u, size, 1.4 * u); break;
    case 5: ctx.fillRect(1.2 * u, 1.2 * u, 1.5 * u, 1.5 * u); ctx.fillRect(13.3 * u, 1.2 * u, 1.5 * u, 1.5 * u); break;
    default: ctx.globalAlpha = 0.45; ctx.lineWidth = 1; ctx.strokeRect(1 * u, 1 * u, size - 2 * u, size - 2 * u);
  }
  ctx.restore();
}

export function drawItemGlyph(ctx, cx, cy, size, icon, seed = '') {
  const V = _variant(seed);
  if (!V) { _glyphShape(ctx, cx, cy, size, icon); return; }
  const ox = cx - size / 2, oy = cy - size / 2;
  ctx.save();
  const g = ctx.createLinearGradient(ox, oy, ox + size, oy + size);
  g.addColorStop(0, `hsla(${V.hue},48%,${V.plate ? 19 : 26}%,0.32)`);
  g.addColorStop(1, `hsla(${(V.hue + 55) % 360},52%,11%,0.32)`);
  ctx.fillStyle = g;
  if (V.plate) { ctx.beginPath(); ctx.arc(cx, cy, size * 0.46, 0, 7); ctx.fill(); }
  else ctx.fillRect(ox + 1, oy + 1, size - 2, size - 2);
  ctx.restore();
  let done = false;
  if (typeof document !== 'undefined' && ctx.canvas) {
    try {
      const n = Math.max(2, Math.ceil(size));
      const t = document.createElement('canvas'); t.width = t.height = n;
      _glyphShape(t.getContext('2d'), n / 2, n / 2, size, icon);
      ctx.save();
      ctx.translate(cx, cy); ctx.rotate(V.rot);
      ctx.filter = `hue-rotate(${V.hue}deg) saturate(${V.sat}) brightness(${V.bri})`;
      ctx.drawImage(t, -n / 2, -n / 2);
      ctx.filter = 'none';
      ctx.restore();
      done = true;
    } catch (_) { /* canvas filter unsupported - draw plainly */ }
  }
  if (!done) _glyphShape(ctx, cx, cy, size, icon);
  _mark(ctx, ox, oy, size, V);
}

function _glyphShape(ctx, cx, cy, size, icon) {
  const u = size / 16;
  ctx.save(); ctx.translate(cx - size / 2, cy - size / 2);
  const b = (x, y, w, h, c) => { ctx.fillStyle = c; ctx.fillRect(x * u, y * u, w * u, h * u); };
  let k = icon;
  for (let i = 0; i < 4 && ICON_ALIAS[k]; i++) k = ICON_ALIAS[k];
  switch (k) {
    /* -- guns -- */
    case 'pistol': b(2, 7, 8, 3, '#3a3f4a'); b(8, 6, 3, 2, '#4a505c'); b(3, 9, 2, 4, '#2a2e36'); b(2, 7, 8, 1, '#6a7280'); b(10, 7, 1, 1, '#ffcf4a'); break;
    case 'rifle': b(1, 7, 13, 2, '#2f343e'); b(2, 6, 10, 1, '#565d6b'); b(4, 8, 2, 4, '#241c14'); b(11, 5, 2, 2, '#3a424f'); b(1, 6, 1, 3, '#1a1d24'); break;
    case 'smg': b(2, 6, 8, 3, '#33383f'); b(3, 8, 2, 5, '#20242b'); b(9, 5, 2, 4, '#3f464f'); b(2, 6, 8, 1, '#5a616c'); break;
    /* -- melee -- */
    case 'knife': b(7, 1, 2, 9, '#d7dfef'); b(6, 1, 4, 1, '#f2f6ff'); b(6, 10, 4, 2, '#5a2a1a'); b(5, 12, 6, 2, '#3a1c12'); break;
    case 'katana': b(8, 0, 1, 12, '#e6ecfb'); b(7, 0, 3, 1, '#ffffff'); b(7, 12, 3, 3, '#2a1a2a'); b(6, 13, 5, 1, '#b98cff'); break;
    case 'machete': b(6, 2, 4, 8, '#c2ccdd'); b(6, 2, 2, 8, '#e6ecfb'); b(10, 3, 1, 6, '#8894a8'); b(6, 10, 4, 3, '#2a2016'); break;
    case 'bat': b(6, 1, 4, 9, '#b98a58'); b(6, 1, 4, 2, '#d0a878'); b(7, 10, 2, 4, '#8a6a40'); ctx.fillStyle = '#7a5a34'; ctx.fillRect(7 * u, 3 * u, 1 * u, 5 * u); break;
    case 'wrench': b(4, 2, 3, 4, '#8892a3'); b(5, 3, 2, 1, '#0c0d12'); b(6, 5, 3, 8, '#6a7484'); b(6, 5, 3, 1, '#9aa4b6'); break;
    case 'sledge': b(3, 3, 8, 4, '#4a505c'); b(3, 3, 8, 1, '#6a7280'); b(7, 6, 2, 9, '#7a5a34'); break;
    /* -- cyber / tech -- */
    case 'chip': b(3, 4, 10, 8, '#0c1c14'); b(4, 5, 8, 6, '#123a28'); ctx.fillStyle = '#3ce88b'; for (let i = 0; i < 3; i++) { ctx.fillRect(1 * u, (5 + i * 2) * u, 2 * u, 1); ctx.fillRect(13 * u, (5 + i * 2) * u, 2 * u, 1); } b(6, 6, 4, 4, '#0a2018'); break;
    case 'optic': b(3, 5, 10, 6, '#14161d'); ctx.fillStyle = '#66e0ff'; ctx.shadowColor = '#66e0ff'; ctx.shadowBlur = 6 * u; ctx.beginPath(); ctx.arc(8 * u, 8 * u, 3.2 * u, 0, 7); ctx.fill(); ctx.shadowBlur = 0; b(7, 7, 2, 2, '#05060c'); break;
    case 'servo': b(5, 2, 3, 6, '#4a505c'); b(4, 7, 8, 3, '#33383f'); b(6, 9, 5, 5, '#3f464f'); b(4, 7, 8, 1, '#66e0ff'); break;
    case 'deck': b(2, 4, 12, 8, '#12141b'); b(3, 5, 10, 4, '#0c1a22'); ctx.fillStyle = '#66e0ff'; ctx.fillRect(4 * u, 6 * u, 8 * u, 1); ctx.fillRect(4 * u, 7.4 * u, 5 * u, 1); b(4, 10, 3, 1, '#333'); b(9, 10, 3, 1, '#333'); b(13, 5, 1, 6, '#ff2d55'); break;
    case 'phone': b(5, 2, 6, 12, '#14161d'); b(6, 3, 4, 8, '#0e2a34'); ctx.fillStyle = '#3ce88b'; ctx.fillRect(6 * u, 4 * u, 4 * u, 1); b(7, 12, 2, 1, '#3a424f'); break;
    case 'scanner': b(3, 4, 10, 9, '#242a34'); b(4, 5, 8, 4, '#0e1b24'); ctx.fillStyle = '#ff9b45'; ctx.fillRect(5 * u, 6 * u, 6 * u, 1); b(5, 10, 2, 2, '#3a424f'); b(9, 10, 2, 2, '#3a424f'); break;
    /* -- armour -- */
    case 'jacket': b(3, 3, 10, 3, '#2f3a4a'); b(2, 5, 12, 8, '#26303e'); b(2, 5, 12, 1, '#3f4c60'); b(7, 5, 2, 8, '#1a222c'); b(2, 5, 1, 6, '#1c242f'); b(13, 5, 1, 6, '#1c242f'); break;
    case 'coat': b(4, 2, 8, 3, '#20222c'); b(3, 4, 10, 11, '#1a1c24'); b(3, 4, 10, 1, '#2c2f3a'); b(7, 4, 2, 11, '#101218'); break;
    case 'vest': b(4, 3, 8, 2, '#2a303a'); b(3, 4, 10, 8, '#242c38'); b(3, 4, 10, 1, '#3a4454'); b(6, 5, 4, 5, '#161c26'); b(4, 5, 1, 6, '#0e1218'); b(11, 5, 1, 6, '#0e1218'); break;
    case 'helmet': b(4, 3, 8, 5, '#3a424f'); b(4, 3, 8, 1, '#5a636f'); b(3, 7, 10, 2, '#2a303a'); b(5, 8, 6, 2, '#66e0ff44'); break;
    case 'goggles': b(2, 6, 12, 4, '#2a2016'); b(3, 6, 4, 4, '#66e0ff'); b(9, 6, 4, 4, '#66e0ff'); b(7, 7, 2, 1, '#1a140c'); break;
    case 'shades': b(2, 6, 5, 3, '#0c0d12'); b(9, 6, 5, 3, '#0c0d12'); b(7, 7, 2, 1, '#3a3f4a'); b(3, 6, 3, 1, '#66e0ff88'); b(10, 6, 3, 1, '#66e0ff88'); break;
    case 'gloves': b(5, 4, 6, 6, '#20242c'); b(4, 5, 1, 3, '#20242c'); b(11, 5, 1, 3, '#20242c'); b(5, 10, 6, 3, '#161a20'); b(5, 4, 6, 1, '#3a424f'); break;
    case 'boots': b(4, 3, 4, 8, '#241c14'); b(4, 10, 8, 3, '#1a140c'); b(4, 3, 4, 1, '#3a2e1e'); b(4, 12, 8, 1, '#0c0a06'); break;
    case 'pants': b(4, 2, 8, 3, '#2a2e38'); b(4, 5, 3, 9, '#242832'); b(9, 5, 3, 9, '#242832'); b(4, 2, 8, 1, '#3a4048'); break;
    case 'harness': b(4, 2, 8, 2, '#2a241a'); b(4, 3, 2, 10, '#2a241a'); b(10, 3, 2, 10, '#2a241a'); b(6, 6, 4, 2, '#3a3222'); b(6, 9, 4, 2, '#3a3222'); break;
    case 'chain': ctx.strokeStyle = '#c9a94a'; ctx.lineWidth = 1.6 * u; ctx.beginPath(); ctx.arc(8 * u, 8 * u, 4 * u, 0.4, 5.9); ctx.stroke(); b(7, 11, 2, 3, '#8a2a2a'); break;
    /* -- food / drink -- */
    case 'ramen': b(4, 6, 8, 7, '#e8e2d0'); b(4, 6, 8, 2, '#c94a2d'); b(5, 8, 6, 2, '#d8b048'); b(7, 4, 1, 4, '#8a7a5a'); b(9, 4, 1, 4, '#8a7a5a'); break;
    case 'bento': b(3, 5, 10, 8, '#1a1c24'); b(4, 6, 4, 3, '#c98548'); b(9, 6, 3, 3, '#3ce88b'); b(4, 10, 8, 2, '#e8e2d0'); break;
    case 'burrito': b(3, 6, 10, 5, '#d8c088'); b(3, 6, 10, 1, '#e8d4a0'); b(4, 7, 8, 1, '#7a4a2a'); b(4, 9, 8, 1, '#3a6a2a'); break;
    case 'rationbar': b(4, 5, 8, 7, '#7a5a34'); b(4, 5, 8, 2, '#9a7a48'); b(5, 8, 6, 1, '#c9a878'); b(5, 10, 6, 1, '#c9a878'); break;
    case 'can': b(5, 3, 6, 11, '#c94a3a'); b(5, 3, 6, 2, '#e06a55'); b(5, 6, 6, 3, '#e8e2d0'); ctx.fillStyle = '#c94a3a'; ctx.fillRect(6 * u, 7 * u, 4 * u, 1); break;
    case 'bottle': b(7, 2, 2, 3, '#2a5560'); b(6, 4, 4, 10, '#1b3a44'); b(6, 4, 4, 1, '#2a5560'); b(6, 8, 4, 3, '#e8e2d0'); break;
    /* -- meds -- */
    case 'inhaler': b(6, 2, 4, 4, '#e6ebf5'); b(5, 6, 6, 7, '#c94a3a'); b(5, 6, 6, 1, '#e06a55'); b(7, 5, 2, 2, '#8894a8'); break;
    case 'syringe': b(7, 1, 2, 9, '#e6ebf5'); b(6, 2, 4, 5, '#66e0ff88'); b(7, 10, 2, 3, '#8894a8'); b(7, 13, 2, 1, '#aa3548'); b(6, 1, 4, 1, '#cfd6e6'); break;
    /* -- gadgets -- */
    case 'toolkit': b(3, 5, 10, 8, '#242a34'); b(3, 5, 10, 2, '#33383f'); b(6, 4, 4, 1, '#3a424f'); ctx.fillStyle = '#ffcf4a'; ctx.fillRect(5 * u, 8 * u, 2 * u, 3 * u); ctx.fillRect(9 * u, 8 * u, 2 * u, 3 * u); break;
    case 'lockpick': b(3, 8, 9, 2, '#8892a3'); b(11, 6, 2, 5, '#6a7484'); b(3, 10, 3, 3, '#20242c'); break;
    case 'grapple': b(4, 6, 8, 5, '#2a2e38'); b(4, 6, 8, 1, '#3a424f'); ctx.strokeStyle = '#8892a3'; ctx.lineWidth = 1.4 * u; ctx.beginPath(); ctx.moveTo(8 * u, 6 * u); ctx.lineTo(8 * u, 1 * u); ctx.stroke(); b(6, 0, 4, 2, '#8892a3'); break;
    case 'grenade': b(6, 4, 4, 8, '#2f5a2a'); b(6, 4, 4, 2, '#3f6a3a'); b(7, 2, 2, 2, '#3a424f'); b(6, 8, 4, 1, '#1f3a1f'); break;
    case 'keycard': b(3, 5, 10, 6, '#1b2f3a'); b(3, 5, 10, 2, '#2a5560'); ctx.fillStyle = '#ffcf4a'; ctx.fillRect(4 * u, 8 * u, 3 * u, 2 * u); b(10, 6, 2, 4, '#0c0d12'); break;
    case 'flashlight': b(4, 6, 8, 4, '#2a2e38'); b(12, 5, 2, 6, '#ffe08a'); ctx.fillStyle = '#ffcf4a'; ctx.shadowColor = '#ffcf4a'; ctx.shadowBlur = 6 * u; ctx.fillRect(13 * u, 6 * u, 2 * u, 4 * u); ctx.shadowBlur = 0; break;
    /* -- containers -- */
    case 'bag': b(3, 5, 10, 9, '#3a2f22'); b(5, 3, 6, 3, '#2a2016'); b(3, 5, 10, 1, '#4a3d2c'); b(7, 8, 2, 3, '#1c150e'); break;
    case 'lockbox': b(3, 5, 10, 8, '#3a3f4a'); b(3, 5, 10, 1, '#565d6b'); b(7, 4, 2, 2, '#565d6b'); b(7, 8, 2, 3, '#ffcf4a'); break;
    /* -- lore / junk -- */
    case 'book': b(4, 3, 8, 10, '#7a2a2a'); b(4, 3, 2, 10, '#5a1c1c'); b(6, 3, 6, 1, '#e8e2d0'); b(6, 5, 5, 1, '#e8e2d0'); b(6, 7, 5, 1, '#e8e2d0'); break;
    case 'disc': ctx.fillStyle = '#8894a8'; ctx.beginPath(); ctx.arc(8 * u, 8 * u, 5.5 * u, 0, 7); ctx.fill(); ctx.fillStyle = '#b98cff'; ctx.beginPath(); ctx.arc(8 * u, 8 * u, 4 * u, 0, 7); ctx.fill(); ctx.fillStyle = '#14161d'; ctx.beginPath(); ctx.arc(8 * u, 8 * u, 1.4 * u, 0, 7); ctx.fill(); break;
    case 'key': b(4, 6, 4, 4, '#c9a94a'); b(6, 7, 1, 2, '#14161d'); b(8, 7, 6, 2, '#c9a94a'); b(12, 9, 1, 2, '#c9a94a'); b(13, 9, 1, 1, '#c9a94a'); break;
    case 'shard': b(6, 2, 4, 12, '#2a1a3a'); ctx.fillStyle = '#b98cffaa'; ctx.fillRect(6 * u, 2 * u, 4 * u, 12 * u); ctx.fillStyle = '#d8c0ff'; ctx.fillRect(7 * u, 3 * u, 1.6 * u, 9 * u); break;
    case 'circuit': case 'servo2': b(3, 4, 10, 8, '#1c2a1c'); ctx.fillStyle = '#c9a94a'; for (let i = 0; i < 4; i++) ctx.fillRect(2 * u, (5 + i * 1.6) * u, 2 * u, 1); b(6, 6, 3, 3, '#2a3a2a'); break;
    case 'cable': ctx.strokeStyle = '#c9622a'; ctx.lineWidth = 2 * u; ctx.beginPath(); ctx.arc(6 * u, 8 * u, 3.5 * u, 0, 7); ctx.arc(10 * u, 8 * u, 3.5 * u, 0, 7); ctx.stroke(); break;
    case 'tail': ctx.strokeStyle = '#8a5a5a'; ctx.lineWidth = 1.6 * u; ctx.beginPath(); ctx.moveTo(3 * u, 12 * u); ctx.quadraticCurveTo(9 * u, 3 * u, 13 * u, 9 * u); ctx.stroke(); break;
    case 'eddies': ctx.fillStyle = '#ffcf4a'; for (let i = 0; i < 3; i++) ctx.fillRect((4 + i) * u, (10 - i) * u, 8 * u, 2 * u); ctx.fillStyle = '#b98a1e'; ctx.fillRect(5 * u, 9 * u, 6 * u, 1); break;
    case 'scrap': b(3, 8, 5, 4, '#3a3020'); b(8, 6, 5, 6, '#2f2a1c'); b(6, 10, 4, 3, '#222'); b(9, 7, 2, 2, '#66e0ff44'); break;
    default: b(4, 5, 8, 8, '#2a2f38'); b(4, 5, 8, 1, '#3d4450'); ctx.fillStyle = '#777'; ctx.fillRect(6 * u, 8 * u, 4 * u, 2 * u);
  }
  ctx.restore();
}

export function drawGroundItem(ctx, cx, cy, T, icon, tt, seed = '') {
  const bob = Math.sin(tt / 260) * 2;
  ctx.save();
  ctx.globalAlpha = 0.35;
  ctx.fillStyle = '#66e0ff';
  ctx.beginPath(); ctx.ellipse(cx, cy + 8, 9, 3, 0, 0, 7); ctx.fill();
  ctx.globalAlpha = 1;
  ctx.shadowColor = '#66e0ff'; ctx.shadowBlur = 10;
  drawItemGlyph(ctx, cx, cy + bob, T * 0.62, icon, seed);
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

/* an offscreen canvas for a UI icon (inventory grid etc.) - memoised so
   repeated draws of the same item are cheap and stay pixel-identical */
const _iconMemo = new Map();
export function iconCanvas(icon, size = 44, seed = '') {
  const key = icon + '|' + size + '|' + seed;
  const hit = _iconMemo.get(key);
  if (hit) return hit;
  const c = document.createElement('canvas');
  c.width = c.height = size;
  drawItemGlyph(c.getContext('2d'), size / 2, size / 2, size * 0.9, icon, seed);
  if (_iconMemo.size > 2400) _iconMemo.clear();
  _iconMemo.set(key, c);
  return c;
}
export function actorCanvas(kind, size = 30) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  drawActor(c.getContext('2d'), size / 2, size * 0.62, size * 1.15, kind, { tt: 0 });
  return c;
}
