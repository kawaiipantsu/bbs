/* scene.js - the canvas game view: tiled room, sprites, movement, fx. */
import { drawTile, drawProp, drawGroundItem, drawActor, THEME } from './sprites.js';
import { preloadAtlas } from './atlas.js';

const INDOOR_THEMES = new Set(['tunnel']);   // rendered as a walled room, not a street

const GW = 13, GH = 9;                 // logical tiles per room
const MID_X = 6, MID_Y = 4;
const EDGE = {                          // exit dir -> border tile it opens
  n: [MID_X, 0], s: [MID_X, GH - 1], e: [GW - 1, MID_Y], w: [0, MID_Y],
  ne: [GW - 1, 0], nw: [0, 0], se: [GW - 1, GH - 1], sw: [0, GH - 1],
};
const ENTER_FROM = { n: [MID_X, 1], s: [MID_X, GH - 2], e: [GW - 2, MID_Y], w: [1, MID_Y],
  ne: [GW - 2, 1], nw: [1, 1], se: [GW - 2, GH - 2], sw: [1, GH - 2] };
const OPP = { n: 's', s: 'n', e: 'w', w: 'e', ne: 'sw', sw: 'ne', nw: 'se', se: 'nw', u: 'd', d: 'u', in: 'out', out: 'in' };

const PROPS = { street: ['neon', 'sign', 'trash', 'car'], corpo: ['terminal', 'plant', 'sign'],
  ruin: ['rubble', 'car', 'trash'], tunnel: ['pipe', 'barrel', 'rubble'],
  arcade: ['neon', 'terminal', 'sign'], desert: ['car', 'crate', 'rubble'], grid: ['terminal', 'neon'] };

function rng(seed) { let s = seed >>> 0 || 1; return () => (s = (s * 1664525 + 1013904223) >>> 0) / 4294967296; }

export class Scene {
  constructor() {
    this.listeners = {};
    this.tt = 0;
    this._shake = 0;
    this.floats = [];
    this.particles = [];
    this.busy = false;
    this.enteredFrom = null;
    this.roomVnum = null;
    this.moveTarget = null;
  }
  on(evt, fn) { (this.listeners[evt] || (this.listeners[evt] = [])).push(fn); }
  emit(evt, d) { (this.listeners[evt] || []).forEach(f => f(d)); }

  mount(el) {
    this.host = el;
    this.canvas = document.createElement('canvas');
    el.appendChild(this.canvas);
    this.ctx = this.canvas.getContext('2d');
    this._resize();
    this._ro = new ResizeObserver(() => this._resize());
    this._ro.observe(el);
    addEventListener('keydown', this._key = e => this._onKey(e));
    this.canvas.addEventListener('pointerdown', e => this._onClick(e));
    preloadAtlas();
    this._raf = requestAnimationFrame(t => this._loop(t));
  }
  destroy() {
    cancelAnimationFrame(this._raf); this._ro && this._ro.disconnect();
    removeEventListener('keydown', this._key);
  }

  _resize() {
    const dpr = Math.min(2, devicePixelRatio || 1);
    const w = this.host.clientWidth, h = this.host.clientHeight;
    this.canvas.width = w * dpr; this.canvas.height = h * dpr;
    this.canvas.style.width = w + 'px'; this.canvas.style.height = h + 'px';
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    this.vw = w; this.vh = h;
    this.T = Math.max(28, Math.floor(Math.min(w / (GW + 1.5), h / (GH + 1.5))));
  }

  /* ---- room state ---- */
  setRoom(room, player) {
    this.player = player;
    const changed = room.vnum !== this.roomVnum;
    this.room = room;
    if (!changed) { this._syncEntities(room); return; }
    this.roomVnum = room.vnum;
    this.theme = room.theme || 'street';
    this._buildGrid(room);
    this._placeEntities(room);
    // player position
    let start = [MID_X, MID_Y];
    if (this.enteredFrom && ENTER_FROM[this.enteredFrom]) start = ENTER_FROM[this.enteredFrom].slice();
    else if (room._portalSpot) start = [room._portalSpot[0], room._portalSpot[1] + 1 <= GH - 2 ? room._portalSpot[1] + 1 : room._portalSpot[1]];
    if (this._blocked(start[0], start[1])) start = this._nearestFree(MID_X, MID_Y);
    this.pc = { gx: start[0], gy: start[1], px: start[0] * this.T, py: start[1] * this.T, face: this.enteredFrom ? OPP[this.enteredFrom] || 's' : 's', walk: false };
    this.busy = false; this.moveTarget = null;
    this._spawnParticles();
    this.emit('rendered', room);
  }

  _buildGrid(room) {
    const r = rng((room.vnum || 1) * 2654435761);
    const indoor = INDOOR_THEMES.has(this.theme) || room.indoors;
    this.indoorRoom = indoor;
    this.grid = [];
    const exitDirs = new Set((room.exits || []).filter(e => !e.hidden && EDGE[e.dir]).map(e => e.dir));

    for (let y = 0; y < GH; y++) {
      this.grid[y] = [];
      for (let x = 0; x < GW; x++) {
        const border = x === 0 || y === 0 || x === GW - 1 || y === GH - 1;
        let role;
        if (border) role = indoor ? 'wall' : 'building';
        else if (indoor) role = 'floor';
        else {
          // outdoor: a crossroads - road strip on MID row/col, sidewalk elsewhere
          const onRoadV = x === MID_X && (exitDirs.has('n') || exitDirs.has('s'));
          const onRoadH = y === MID_Y && (exitDirs.has('e') || exitDirs.has('w'));
          if (onRoadV && onRoadH) role = 'roadline';
          else if (onRoadV || onRoadH) role = 'road';
          else role = 'sidewalk';
        }
        this.grid[y][x] = { wall: border, role, prop: null, exit: null, seed: (x * 31 + y * 17 + (room.vnum || 1)) };
      }
    }
    if (!indoor && !exitDirs.size) {
      // isolated outdoor room: give it a little plaza of sidewalk
      for (let y = 1; y < GH - 1; y++) for (let x = 1; x < GW - 1; x++) this.grid[y][x].role = 'sidewalk';
    }

    // open exits in the border -> door tiles
    this.exitTiles = {};
    for (const ex of room.exits || []) {
      if (ex.hidden) continue;
      const spot = EDGE[ex.dir];
      if (spot) {
        this.grid[spot[1]][spot[0]] = { wall: false, role: 'door', prop: null, exit: ex, seed: 1 };
        this.exitTiles[ex.dir] = spot;
      }
    }

    // vertical / interior exits -> a portal near a free inner tile
    const verticals = (room.exits || []).filter(e => !EDGE[e.dir] && !e.hidden);
    if (verticals.length) {
      const spot = [MID_X + (r() < .5 ? -2 : 2), MID_Y + (r() < .5 ? -1 : 1)];
      spot[0] = Math.max(2, Math.min(GW - 3, spot[0])); spot[1] = Math.max(2, Math.min(GH - 3, spot[1]));
      this.grid[spot[1]][spot[0]].prop = 'portal';
      this.grid[spot[1]][spot[0]].portalExits = verticals;
      room._portalSpot = spot;
    } else room._portalSpot = null;

    // decorate: windows/doors on the non-exit border, props on the pavement
    const props = indoor
      ? ['acbox', 'crate', 'barrel', 'terminal']
      : (this.theme === 'desert' ? ['car', 'crate', 'cone', 'barrier']
        : this.theme === 'ruin' ? ['rubble', 'car', 'trash', 'cone']
        : ['neon', 'tree', 'dumpster', 'vending', 'car', 'awning', 'sign', 'hydrant']);
    // window/door dressing on solid border walls (visual only)
    for (let x = 2; x < GW - 2; x += 2) {
      for (const y of [0, GH - 1]) {
        const c = this.grid[y]?.[x];
        if (c && c.wall && !c.exit && r() < 0.6) c.dress = r() < 0.4 ? 'door' : 'window';
      }
    }
    // scatter 3-5 solid props on sidewalk/floor tiles, away from the crossroads
    let placed = 0, tries = 0;
    while (placed < 4 + ((r() * 3) | 0) && tries++ < 40) {
      const x = 1 + ((r() * (GW - 2)) | 0), y = 1 + ((r() * (GH - 2)) | 0);
      const c = this.grid[y][x];
      if (c.wall || c.prop || c.exit || (x === MID_X && y === MID_Y)) continue;
      if (!indoor && (c.role === 'road' || c.role === 'roadline')) continue; // keep the road clear
      c.prop = props[(r() * props.length) | 0];
      c.solid = c.prop !== 'car' ? true : true;
      placed++;
    }
  }

  _placeEntities(room) {
    const r = rng((room.vnum || 1) * 40503 + 7);
    this.ents = [];
    const free = [];
    for (let y = 1; y < GH - 1; y++) for (let x = 1; x < GW - 1; x++) {
      const c = this.grid[y][x];
      if (!c.wall && !c.solid && !c.prop && !(x === MID_X && y === MID_Y)) free.push([x, y]);
    }
    const take = () => free.length ? free.splice((r() * free.length) | 0, 1)[0] : [MID_X + 1, MID_Y];
    for (const m of room.mobs || []) { const [x, y] = take(); this.ents.push({ kind: 'mob', gx: x, gy: y, data: m, bob: r() * 6 }); }
    for (const it of room.items || []) { const [x, y] = take(); this.ents.push({ kind: 'item', gx: x, gy: y, data: it }); }
  }

  _syncEntities(room) {
    // keep positions, update/remove/add
    const byId = new Map(this.ents.filter(e => e.kind === 'mob').map(e => [e.data.id, e]));
    const seen = new Set();
    for (const m of room.mobs || []) {
      seen.add(m.id);
      const e = byId.get(m.id);
      if (e) e.data = m; else { const s = this._freeSpot(); this.ents.push({ kind: 'mob', gx: s[0], gy: s[1], data: m }); }
    }
    this.ents = this.ents.filter(e => e.kind !== 'mob' || seen.has(e.data.id));
    // items
    const itIds = new Set((room.items || []).map(i => i.id));
    const haveIt = new Set(this.ents.filter(e => e.kind === 'item').map(e => e.data.id));
    for (const it of room.items || []) if (!haveIt.has(it.id)) { const s = this._freeSpot(); this.ents.push({ kind: 'item', gx: s[0], gy: s[1], data: it }); }
    this.ents = this.ents.filter(e => e.kind !== 'item' || itIds.has(e.data.id));
  }
  _freeSpot() {
    for (let i = 0; i < 40; i++) { const x = 1 + ((Math.random() * (GW - 2)) | 0), y = 1 + ((Math.random() * (GH - 2)) | 0); if (!this._blocked(x, y) && !this._entAt(x, y)) return [x, y]; }
    return [MID_X + 1, MID_Y];
  }
  _nearestFree(x, y) { for (let rad = 0; rad < 6; rad++) for (let dy = -rad; dy <= rad; dy++) for (let dx = -rad; dx <= rad; dx++) { const nx = x + dx, ny = y + dy; if (nx > 0 && ny > 0 && nx < GW - 1 && ny < GH - 1 && !this._blocked(nx, ny)) return [nx, ny]; } return [MID_X, MID_Y]; }
  _blocked(x, y) { const c = this.grid?.[y]?.[x]; return !c || (c.wall && !c.exit) || c.solid; }
  _entAt(x, y) { return this.ents.find(e => e.gx === x && e.gy === y); }

  /* ---- input ---- */
  _onKey(e) {
    if (e.target && /input|textarea/i.test(e.target.tagName)) return;
    const map = { ArrowUp: 'n', KeyW: 'n', ArrowDown: 's', KeyS: 's', ArrowLeft: 'w', KeyA: 'w', ArrowRight: 'e', KeyD: 'e', KeyQ: 'nw', KeyE: 'ne', KeyZ: 'sw', KeyC: 'se' };
    const dir = map[e.code];
    if (dir) { e.preventDefault(); this.step(dir); }
    else if (e.code === 'Space' || e.code === 'KeyF') { e.preventDefault(); this._interactFacing(); }
  }
  _onClick(e) {
    const rect = this.canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left - this.ox, my = e.clientY - rect.top - this.oy;
    const gx = Math.floor(mx / this.T), gy = Math.floor(my / this.T);
    if (gx < 0 || gy < 0 || gx >= GW || gy >= GH) return;
    const c = this.grid?.[gy]?.[gx];
    if (c?.exit) { this._takeExit(c.exit); return; }
    if (c?.portalExits) { this._portalMenu(c.portalExits); return; }
    const ent = this._entAt(gx, gy);
    if (ent) {
      if (Math.abs(gx - this.pc.gx) + Math.abs(gy - this.pc.gy) <= 1) { this._bump(ent); return; }
      // walk to an adjacent free tile, then interact
      const near = this._adjacentTo(gx, gy);
      this.moveTarget = near;
      this._pendingInteract = { id: ent.kind === 'mob' ? ent.data.id : 'i' + ent.data.id, gx, gy };
      return;
    }
    this._pendingInteract = null;
    this.moveTarget = [gx, gy];
  }
  _adjacentTo(gx, gy) {
    let best = null, bd = 1e9;
    for (const [dx, dy] of [[0, 1], [0, -1], [1, 0], [-1, 0], [1, 1], [1, -1], [-1, 1], [-1, -1]]) {
      const nx = gx + dx, ny = gy + dy;
      if (nx <= 0 || ny <= 0 || nx >= GW - 1 || ny >= GH - 1 || this._blocked(nx, ny) || this._entAt(nx, ny)) continue;
      const d = Math.abs(nx - this.pc.gx) + Math.abs(ny - this.pc.gy);
      if (d < bd) { bd = d; best = [nx, ny]; }
    }
    return best || [this.pc.gx, this.pc.gy];
  }

  _dirTo(dx, dy) {
    if (dx > 0 && dy < 0) return 'ne'; if (dx > 0 && dy > 0) return 'se';
    if (dx < 0 && dy < 0) return 'nw'; if (dx < 0 && dy > 0) return 'sw';
    if (dx > 0) return 'e'; if (dx < 0) return 'w'; if (dy < 0) return 'n'; return 's';
  }

  step(dir) {
    if (this.busy || !this.pc) return;
    const d = { n: [0, -1], s: [0, 1], e: [1, 0], w: [-1, 0], ne: [1, -1], nw: [-1, -1], se: [1, 1], sw: [-1, 1] }[dir];
    if (!d) return;
    const nx = this.pc.gx + d[0], ny = this.pc.gy + d[1];
    this.pc.face = dir;
    const c = this.grid?.[ny]?.[nx];
    if (c?.exit) { this._takeExit(c.exit); return; }
    const ent = this._entAt(nx, ny);
    if (ent) { this._bump(ent); return; }
    if (nx <= 0 || ny <= 0 || nx >= GW - 1 || ny >= GH - 1 || this._blocked(nx, ny)) {
      // nudge - maybe player wants an exit just past a corner
      this.emit('bump-wall'); return;
    }
    this.pc.gx = nx; this.pc.gy = ny; this.pc.walk = true; this._startWalk();
    if (c?.prop === 'portal') { this._portalMenu(c.portalExits); }
  }
  _startWalk() {
    this.busy = true;
    clearTimeout(this._wt);
    this._wt = setTimeout(() => { this.busy = false; this.pc.walk = false; }, 130);
  }
  _interactFacing() {
    const d = { n: [0, -1], s: [0, 1], e: [1, 0], w: [-1, 0], ne: [1, -1], nw: [-1, -1], se: [1, 1], sw: [-1, 1] }[this.pc.face] || [0, 1];
    const nx = this.pc.gx + d[0], ny = this.pc.gy + d[1];
    const c = this.grid?.[ny]?.[nx];
    if (c?.exit) return this._takeExit(c.exit);
    const ent = this._entAt(nx, ny);
    if (ent) return this._bump(ent);
    if (c?.portalExits) return this._portalMenu(c.portalExits);
  }
  _bump(ent) {
    if (ent.kind === 'item') this.emit('interact', { type: 'item', item: ent.data });
    else {
      const m = ent.data;
      this.emit('interact', { type: 'mob', mob: m, hostile: m.hostile || m.state === 'fighting' });
    }
  }
  _takeExit(ex) {
    if (this.busy) return;
    this.enteredFrom = OPP[ex.dir] || null;
    this.emit('exit', ex);
  }
  _portalMenu(exits) {
    this.enteredFrom = null;
    this.emit('portal', exits);
  }

  /* ---- fx ---- */
  float(text, color = '#fff', at) {
    const p = at || { gx: this.pc.gx, gy: this.pc.gy };
    this.floats.push({ text, color, x: p.gx * this.T + this.T / 2, y: p.gy * this.T, life: 1 });
  }
  shake(n) { this._shake = Math.max(this._shake, n); }
  pulse(kind) {
    if (kind === 'levelup') { this.float('LEVEL UP', '#ffcf4a'); this._flash = { c: '#ffcf4a', a: .5 }; }
    else if (kind === 'death') { this._flash = { c: '#ff2d55', a: .7 }; }
    else if (kind === 'hackok') { this._flash = { c: '#66e0ff', a: .3 }; }
    else if (kind === 'quest') { this.float('JOB', '#b98cff'); }
  }
  floatAtMob(id, text, color) {
    const e = this.ents.find(x => x.kind === 'mob' && x.data.id === id);
    if (e) this.float(text, color, e);
  }

  /* ---- graphic battle: queue of parsed combat rounds ---- */
  playBattle(mobId, events) {
    if (!events || !events.length) return;
    const e = this.ents.find(x => x.kind === 'mob' && x.data.id === mobId);
    this._battleMob = e || null;
    this._battleAt = e ? { gx: e.gx, gy: e.gy } : { gx: this.pc.gx, gy: Math.max(1, this.pc.gy - 1) };
    this._battleQ = events.slice(0, 14);
    this._battleT = 0;
    this.pc.face = this._dirTo(Math.sign(this._battleAt.gx - this.pc.gx), Math.sign(this._battleAt.gy - this.pc.gy));
  }
  _stepBattle(dt) {
    if (!this._battleQ || !this._battleQ.length) { this._slashes = (this._slashes || []).filter(s => s.life > 0); return; }
    this._battleT -= dt;
    if (this._battleT > 0) return;
    const ev = this._battleQ.shift();
    this._battleT = 240;
    const T = this.T;
    const from = ev.src === 'you' ? this.pc : this._battleAt;
    const to = ev.src === 'you' ? this._battleAt : this.pc;
    const fx = from.px != null ? from.px + T / 2 : from.gx * T + T / 2;
    const fy = from.py != null ? from.py + T / 2 : from.gy * T + T / 2;
    const tx = to.px != null ? to.px + T / 2 : to.gx * T + T / 2;
    const ty = to.py != null ? to.py + T / 2 : to.gy * T + T / 2;
    // lunge
    if (ev.src === 'you') { this._lunge = { dx: Math.sign(tx - fx) * T * 0.4, dy: Math.sign(ty - fy) * T * 0.4, life: 1 }; }
    (this._slashes = this._slashes || []).push({ x: tx, y: ty, kind: ev.kind || 'swing', life: 1, miss: ev.miss });
    if (ev.miss) { this.float('miss', '#8b90b2', { gx: (tx / T) | 0, gy: (ty / T) | 0 }); }
    else {
      const col = ev.src === 'you' ? (ev.crit ? '#ffcf4a' : '#66e0ff') : '#ff3b57';
      this.float((ev.crit ? '!' : '') + '-' + (ev.dmg || 0), col, { gx: (tx / T) | 0, gy: (ty / T - 0.3) | 0 });
      if (ev.crit) this.shake(7); else this.shake(3);
      if (ev.src === 'mob') this._flash = { c: '#ff2d55', a: 0.22 };
    }
    if (ev.killed && this._battleMob) {
      this._poof = { x: tx, y: ty, life: 1 };
      this.pulse('death'); this._flash = { c: '#ff2d55', a: 0 };
    }
  }
  _drawBattleFx(ctx) {
    const now = this.tt;
    for (const s of (this._slashes || [])) {
      s.life -= 0.06;
      if (s.life <= 0) continue;
      ctx.save(); ctx.globalAlpha = s.life;
      const R = this.T * 0.5;
      if (s.miss) { ctx.strokeStyle = '#8b90b2'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(s.x, s.y, R * (1 - s.life) + 4, 0, 6); ctx.stroke(); }
      else if (s.kind === 'gun') {
        ctx.fillStyle = '#ffd76a'; ctx.shadowColor = '#ffcf4a'; ctx.shadowBlur = 12;
        for (let i = 0; i < 6; i++) { const a = (i / 6) * 7 + now / 50; ctx.fillRect(s.x + Math.cos(a) * R * (1 - s.life) * 1.5, s.y + Math.sin(a) * R * (1 - s.life) * 1.5, 3, 3); }
      } else {
        ctx.strokeStyle = s.kind === 'blade' ? '#e8f0ff' : '#ff6a88'; ctx.lineWidth = 3; ctx.shadowColor = ctx.strokeStyle; ctx.shadowBlur = 10;
        ctx.beginPath(); ctx.arc(s.x, s.y, R, -1 + (1 - s.life) * 3, 1.6 + (1 - s.life) * 3); ctx.stroke();
      }
      ctx.restore();
    }
    this._slashes = (this._slashes || []).filter(s => s.life > 0);
    if (this._poof) {
      this._poof.life -= 0.04;
      if (this._poof.life > 0) {
        ctx.save(); ctx.globalAlpha = this._poof.life; ctx.fillStyle = '#9aa0c0';
        const n = 10, rad = this.T * (1 - this._poof.life) * 0.9;
        for (let i = 0; i < n; i++) { const a = i / n * 7; ctx.fillRect(this._poof.x + Math.cos(a) * rad, this._poof.y + Math.sin(a) * rad, 3, 3); }
        ctx.restore();
      } else this._poof = null;
    }
  }

  _spawnParticles() {
    this.particles = [];
    const outdoor = !this.room.indoors && !this.room.dark;
    const kind = this.theme === 'desert' ? 'dust' : (outdoor ? 'rain' : (this.theme === 'grid' || this.theme === 'blackwall' ? 'data' : null));
    this._pkind = kind;
    if (!kind) return;
    const n = kind === 'rain' ? 90 : kind === 'dust' ? 40 : 30;
    for (let i = 0; i < n; i++) this.particles.push({ x: Math.random(), y: Math.random(), s: Math.random() });
  }

  /* ---- render loop ---- */
  _loop(t) {
    const dt = Math.min(50, t - (this._last || t)); this._last = t; this.tt += dt;
    this._raf = requestAnimationFrame(tt => this._loop(tt));
    if (!this.room || !this.pc) { this._clear(); return; }
    this._stepBattle(dt);
    if (this._lunge) { this._lunge.life -= 0.12; if (this._lunge.life <= 0) this._lunge = null; }

    // auto-walk toward moveTarget
    if (this.moveTarget && !this.busy) {
      const [tx, ty] = this.moveTarget;
      if (tx === this.pc.gx && ty === this.pc.gy) {
        this.moveTarget = null;
        if (this._pendingInteract) {
          const pi = this._pendingInteract; this._pendingInteract = null;
          const ent = this._entAt(pi.gx, pi.gy);
          if (ent) { this.pc.face = this._dirTo(Math.sign(pi.gx - this.pc.gx), Math.sign(pi.gy - this.pc.gy)); this._bump(ent); }
        }
      } else {
        const dx = Math.sign(tx - this.pc.gx), dy = Math.sign(ty - this.pc.gy);
        const before = [this.pc.gx, this.pc.gy];
        this.step(this._dirTo(dx, dy));
        if (before[0] === this.pc.gx && before[1] === this.pc.gy) { this.moveTarget = null; this._pendingInteract = null; }
      }
    }

    // tween pc pixel pos
    const tgx = this.pc.gx * this.T, tgy = this.pc.gy * this.T;
    this.pc.px += (tgx - this.pc.px) * 0.3;
    this.pc.py += (tgy - this.pc.py) * 0.3;

    const ctx = this.ctx;
    const roomPxW = GW * this.T, roomPxH = GH * this.T;
    this.ox = Math.round((this.vw - roomPxW) / 2);
    this.oy = Math.round((this.vh - roomPxH) / 2);
    let sx = 0, sy = 0;
    if (this._shake > 0) { sx = (Math.random() - .5) * this._shake; sy = (Math.random() - .5) * this._shake; this._shake *= 0.85; if (this._shake < .4) this._shake = 0; }

    this._clear();
    ctx.save();
    ctx.translate(this.ox + sx, this.oy + sy);

    const th = THEME[this.theme] || THEME.street;
    // ground / roads first, then the building border on top so facades overlap
    for (let y = 0; y < GH; y++) for (let x = 0; x < GW; x++) {
      const c = this.grid[y][x];
      if (c.wall && !c.exit) continue;
      drawTile(ctx, x * this.T, y * this.T, this.T, this.theme, c.seed, c.role || 'floor');
    }
    for (let y = 0; y < GH; y++) for (let x = 0; x < GW; x++) {
      const c = this.grid[y][x];
      if (!c.wall || c.exit) continue;
      drawTile(ctx, x * this.T, y * this.T, this.T, this.theme, c.seed, c.role || 'building');
      if (c.dress) drawProp(ctx, x * this.T, y * this.T, this.T, this.theme, c.dress, c.seed);
    }
    // exit glows
    for (const ex of this.room.exits || []) {
      const spot = this.exitTiles[ex.dir];
      if (!spot) continue;
      const [ex_, ey_] = spot;
      const cx = ex_ * this.T + this.T / 2, cy = ey_ * this.T + this.T / 2;
      ctx.save();
      const col = ex.locked ? '#ff2d55' : th.glow;
      ctx.globalAlpha = 0.35 + 0.35 * Math.sin(this.tt / 400);
      ctx.strokeStyle = col; ctx.lineWidth = 3; ctx.shadowColor = col; ctx.shadowBlur = 14;
      ctx.strokeRect(ex_ * this.T + 3, ey_ * this.T + 3, this.T - 6, this.T - 6);
      ctx.globalAlpha = 1;
      const chev = { n: '▲', s: '▼', e: '▶', w: '◀' }[ex.dir] || '◆';
      ctx.fillStyle = col; ctx.font = `bold ${Math.round(this.T * 0.4)}px sans-serif`;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(ex.locked ? '\u{1F512}' : chev, cx, cy);
      ctx.restore();
    }
    // props
    for (let y = 0; y < GH; y++) for (let x = 0; x < GW; x++) {
      const c = this.grid[y][x];
      if (c.prop === 'portal') {
        const dirs = (c.portalExits || []).map(e => e.dir);
        const kind = dirs.includes('u') && !dirs.includes('d') ? 'stairsup'
          : dirs.includes('d') && !dirs.includes('u') ? 'stairsdown' : 'stairs';
        drawProp(ctx, x * this.T, y * this.T, this.T, this.theme, kind, c.seed);
        ctx.save();
        ctx.globalAlpha = 0.22 + 0.22 * Math.sin(this.tt / 300);
        ctx.strokeStyle = th.glow; ctx.lineWidth = 2; ctx.shadowColor = th.glow; ctx.shadowBlur = 10;
        ctx.strokeRect(x * this.T + 2.5, y * this.T + 2.5, this.T - 5, this.T - 5);
        ctx.restore();
      } else if (c.prop) drawProp(ctx, x * this.T, y * this.T, this.T, this.theme, c.prop, c.seed);
    }
    // sort entities + player by y for depth
    const drawList = [...this.ents.map(e => ({ e, y: e.gy })), { pc: true, y: this.pc.gy }];
    drawList.sort((a, b) => a.y - b.y);
    for (const d of drawList) {
      if (d.pc) {
        const lx = this._lunge ? this._lunge.dx * this._lunge.life : 0;
        const ly = this._lunge ? this._lunge.dy * this._lunge.life : 0;
        drawActor(ctx, this.pc.px + this.T / 2 + lx, this.pc.py + this.T * 0.9 + ly, this.T * 1.15,
          this.player?.archetype || 'netrunner', { tt: this.tt, facing: this.pc.face, walk: this.pc.walk, boss: false });
      } else {
        const e = d.e;
        const cx = e.gx * this.T + this.T / 2, cy = e.gy * this.T + this.T * 0.9;
        if (e.kind === 'item') drawGroundItem(ctx, cx, e.gy * this.T + this.T / 2, this.T, e.data.icon, this.tt, e.data.name || e.data.kw || '');
        else {
          const m = e.data;
          drawActor(ctx, cx, cy, this.T * 1.12, m.sprite || 'civ', { tt: this.tt + e.bob * 100, boss: m.boss, hurt: m.state === 'fighting', facing: 's' });
          // name + hp for hostiles/bosses
          if (m.hostile || m.boss || m.state === 'fighting') {
            const bx = e.gx * this.T + 4, bw = this.T - 8;
            ctx.fillStyle = '#2a0f16'; ctx.fillRect(bx, e.gy * this.T - 2, bw, 3);
            ctx.fillStyle = m.boss ? '#ffcf4a' : '#ff3b57'; ctx.fillRect(bx, e.gy * this.T - 2, bw * Math.max(0, m.hpPct), 3);
          }
          if (m.shop || m.trainer || m.ripperdoc || m.questgiver) {
            ctx.fillStyle = '#66e0ff'; ctx.font = `${Math.round(this.T * 0.3)}px sans-serif`; ctx.textAlign = 'center';
            ctx.fillText(m.questgiver ? '❗' : m.shop ? '\u{1F4B0}' : '\u{1F527}', cx, e.gy * this.T - 6);
          }
        }
      }
    }
    // battle fx + particles
    this._drawBattleFx(ctx);
    this._drawParticles(ctx);
    // floating text
    for (const f of this.floats) {
      f.y -= 0.6; f.life -= 0.02;
      ctx.globalAlpha = Math.max(0, f.life);
      ctx.fillStyle = f.color; ctx.font = `bold ${Math.round(this.T * 0.42)}px ui-monospace,monospace`;
      ctx.textAlign = 'center'; ctx.strokeStyle = '#000'; ctx.lineWidth = 3;
      ctx.strokeText(f.text, f.x, f.y); ctx.fillText(f.text, f.x, f.y);
      ctx.globalAlpha = 1;
    }
    this.floats = this.floats.filter(f => f.life > 0);
    ctx.restore();

    // vignette + flash
    const g = ctx.createRadialGradient(this.vw / 2, this.vh / 2, this.vh * 0.3, this.vw / 2, this.vh / 2, this.vh * 0.75);
    g.addColorStop(0, 'transparent'); g.addColorStop(1, '#05060ccc');
    ctx.fillStyle = g; ctx.fillRect(0, 0, this.vw, this.vh);
    if (this._flash) {
      ctx.fillStyle = this._flash.c; ctx.globalAlpha = this._flash.a; ctx.fillRect(0, 0, this.vw, this.vh); ctx.globalAlpha = 1;
      this._flash.a *= 0.86; if (this._flash.a < 0.02) this._flash = null;
    }
  }

  _drawParticles(ctx) {
    if (!this._pkind) return;
    const w = GW * this.T, h = GH * this.T;
    ctx.save();
    for (const p of this.particles) {
      if (this._pkind === 'rain') {
        p.y += 0.02 + p.s * 0.02; p.x += 0.002;
        if (p.y > 1) { p.y = 0; p.x = Math.random(); }
        ctx.strokeStyle = 'rgba(140,200,255,.25)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(p.x * w, p.y * h); ctx.lineTo(p.x * w - 2, p.y * h + 8); ctx.stroke();
      } else if (this._pkind === 'dust') {
        p.x += 0.004 + p.s * 0.004; if (p.x > 1) { p.x = 0; p.y = Math.random(); }
        ctx.fillStyle = 'rgba(255,207,74,.12)'; ctx.fillRect(p.x * w, p.y * h, 2, 1);
      } else {
        p.y -= 0.006; if (p.y < 0) { p.y = 1; p.x = Math.random(); }
        ctx.fillStyle = 'rgba(102,224,255,.3)'; ctx.fillRect(p.x * w, p.y * h, 1, 3);
      }
    }
    ctx.restore();
  }
  _clear() {
    this.ctx.fillStyle = '#05060c';
    this.ctx.fillRect(0, 0, this.vw, this.vh);
  }
}
