/* atlas.js - loads the bundled Kenney CC0 sheets and blits tiles by name.
   kenney.nl - Roguelike Modern City / RPG packs, CC0 1.0 (public domain). */

const SHEETS = {
  city: { src: '/hackers-mud/assets/kenney/city.png', tile: 16, gap: 0 },
  rpg:  { src: '/hackers-mud/assets/kenney/rpg.png',  tile: 16, gap: 1 },
};

/* named frames [col,row] on city.png (16px, no gap) */
export const CITY = {
  road: [10, 20], road_dash: [11, 22], road_yellow: [12, 20], crosswalk: [11, 22],
  sidewalk: [1, 21], sidewalk_tan: [4, 21], grass: [2, 26], dirt: [10, 26], fence: [16, 19],
  wall_brick: [0, 5], wall_grey: [4, 5], wall_tan: [8, 5], wall_glass: [12, 5], wall_glasslit: [13, 4],
  roof_red: [2, 1], roof_grey: [10, 1],
  door_glass: [25, 15], door_wood: [26, 15], door_arch: [28, 15],
  window: [25, 17], window2: [26, 17],
  car_side: [31, 17], car_top: [31, 18], car_side2: [34, 17],
  neon_g: [32, 6], neon_o: [32, 7], neon_t: [33, 6], neon_v: [31, 7],
  tree: [31, 11], tree_a: [32, 11], bush: [31, 13],
  dumpster_o: [28, 7], dumpster_g: [29, 7], cone: [14, 18], pole: [0, 18],
  vending: [24, 8], barrier: [24, 6], awning_g: [24, 10], awning_o: [27, 10], acbox: [31, 14],
};

const cache = {};
let loaded = 0, total = 0;

export function preloadAtlas() {
  return Promise.all(Object.entries(SHEETS).map(([k, s]) => new Promise(res => {
    total++;
    const img = new Image();
    img.onload = () => { cache[k] = img; loaded++; res(); };
    img.onerror = () => { cache[k] = null; loaded++; res(); };
    img.src = s.src;
  })));
}
export const atlasReady = () => total > 0 && loaded >= total;

/** blit sheet frame (col,row) into ctx at dx,dy scaled to dsize x dsize. */
export function blit(sheet, col, row, ctx, dx, dy, dsize) {
  const img = cache[sheet];
  const cfg = SHEETS[sheet];
  if (!img || !cfg) return false;
  const t = cfg.tile, g = cfg.gap;
  ctx.imageSmoothingEnabled = false;
  ctx.drawImage(img, col * (t + g), row * (t + g), t, t, Math.round(dx), Math.round(dy), Math.ceil(dsize), Math.ceil(dsize));
  return true;
}

/** blit a CITY named frame */
export function city(name, ctx, dx, dy, dsize) {
  const f = CITY[name];
  return f ? blit('city', f[0], f[1], ctx, dx, dy, dsize) : false;
}

/** offscreen canvas of a CITY frame, for DOM use */
export function cityCanvas(name, size = 40) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  city(name, c.getContext('2d'), 0, 0, size);
  return c;
}
