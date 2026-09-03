/* worldmap.js - full-city fog-of-war atlas for the graphical MUD client.
 *
 * openWorldMap(container, { hereVnum, hereZ, zoneSlug }) -> cleanup()
 *
 * Fetches /api/mud/worldmap and paints a large zoomable / pannable canvas of
 * every room in the game. Unvisited rooms are drawn as faint fog; rooms one
 * step past the explored frontier ("ghost") a little brighter; visited rooms
 * solid and tinted by zone, with exits, markers and labels. Pure canvas 2D -
 * no external libraries (CSP script-src 'self').
 */
import { S } from './net.js';

const GRID = 46;            // world px between adjacent rooms (one map step)
const ZONE_GAP = 6;         // empty cells left between packed zones
const ZONE_WRAP = 64;       // wrap the zone shelf after this many cells wide
const MIN_SCALE = 0.12;
const MAX_SCALE = 3.2;
const LABEL_SCALE = 0.85;   // show every room label past this zoom
const LS_ZOOM = 'hmud.worldmap.zoom';

const DIR_VEC = {
  n: [0, 1], s: [0, -1], e: [1, 0], w: [-1, 0],
  ne: [1, 1], nw: [-1, 1], se: [1, -1], sw: [-1, -1],
};

function zoneHue(id) { return (id * 47 + 12) % 360; }

async function fetchWorld() {
  const headers = { Accept: 'application/json' };
  if (S && S.csrf) headers['X-CSRF'] = S.csrf;
  const res = await fetch('/api/mud/worldmap', { credentials: 'same-origin', headers });
  const data = await res.json();
  if (!data || !data.ok) throw new Error((data && data.error) || 'map unavailable');
  return data.map;
}

export function openWorldMap(container, opts = {}) {
  const hereVnum = opts.hereVnum || 0;
  const MONO = 'ui-monospace,"JetBrains Mono",Menlo,Consolas,monospace';
  let disposed = false;

  container.innerHTML = `
    <canvas class="wm-canvas"></canvas>
    <div class="wm-ctrls">
      <button data-act="in"  title="Zoom in (+)">+</button>
      <button data-act="out" title="Zoom out (-)">&minus;</button>
      <button data-act="me"  title="Recenter on me">&#8982;</button>
      <button data-act="zup" title="Level up (])">&#9650;</button>
      <button data-act="zdn" title="Level down ([)">&#9660;</button>
      <button data-act="zones" title="Zone index">&#9776;</button>
    </div>
    <div class="wm-hud"></div>
    <div class="wm-zones collapsed"><div class="wm-zones-hd">ZONES</div><div class="wm-zones-list"></div></div>
    <div class="wm-legend"></div>
    <div class="wm-inspect" hidden></div>
    <div class="wm-msg">loading the grid&hellip;</div>`;

  const canvas = container.querySelector('.wm-canvas');
  const ctx = canvas.getContext('2d');
  const hud = container.querySelector('.wm-hud');
  const msg = container.querySelector('.wm-msg');
  const zonesBox = container.querySelector('.wm-zones');
  const zonesList = container.querySelector('.wm-zones-list');
  const legend = container.querySelector('.wm-legend');
  const inspect = container.querySelector('.wm-inspect');

  legend.innerHTML = `
    <span><i style="background:var(--red)"></i>you</span>
    <span><i style="background:#7fd7ff"></i>visited</span>
    <span><i class="o"></i>frontier</span>
    <span><i class="f"></i>fog</span>`;

  // --- state ---------------------------------------------------------------
  let world = null;
  let byVnum = new Map();     // vnum -> laid-out room {..., wx, wy}
  let zones = [];             // [{id,name,hue, rooms:[], zByCount}]
  let zLevels = [];           // sorted unique z present
  let curZ = (opts.hereZ != null ? opts.hereZ : 0);
  let cam = { x: 0, y: 0, scale: 0.6 };
  try { const z = parseFloat(localStorage.getItem(LS_ZOOM)); if (z >= MIN_SCALE && z <= MAX_SCALE) cam.scale = z; } catch (e) {}

  let W = 0, H = 0, dpr = 1;
  let dragging = false, dragMoved = false, lastPt = null;
  let hover = null;           // hovered room
  let selected = null;        // inspected room
  let raf = 0;
  const t0 = performance.now();

  // --- layout: pack each zone's bbox onto a shelf so zones don't overlap --
  function layout() {
    byVnum = new Map();
    const zmap = new Map();
    for (const r of world.rooms) {
      if (!zmap.has(r.zone)) zmap.set(r.zone, { id: r.zone, name: r.zoneName || ('Zone ' + r.zone), rooms: [] });
      zmap.get(r.zone).rooms.push(r);
    }
    zones = [...zmap.values()].sort((a, b) => a.id - b.id);

    let cursorX = 0, rowY = 0, rowH = 0;
    for (const z of zones) {
      z.hue = zoneHue(z.id);
      let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
      for (const r of z.rooms) {
        if (r.x < minX) minX = r.x; if (r.x > maxX) maxX = r.x;
        if (r.y < minY) minY = r.y; if (r.y > maxY) maxY = r.y;
      }
      const wCells = (maxX - minX) + 1, hCells = (maxY - minY) + 1;
      if (cursorX > 0 && cursorX + wCells > ZONE_WRAP) { cursorX = 0; rowY += rowH + ZONE_GAP; rowH = 0; }
      const offX = cursorX - minX;
      const offY = rowY - minY;
      for (const r of z.rooms) {
        r.wx = (r.x + offX) * GRID;
        r.wy = -(r.y + offY) * GRID;           // y up
        r.zoneRef = z;
        byVnum.set(r.vnum, r);
      }
      // zone centroid over visited rooms (fallback: all rooms) per z-level
      z.cxByZ = {};
      const acc = {};
      for (const r of z.rooms) {
        const key = r.z;
        (acc[key] || (acc[key] = { sx: 0, sy: 0, n: 0, vsx: 0, vsy: 0, vn: 0 }));
        acc[key].sx += r.wx; acc[key].sy += r.wy; acc[key].n++;
        if (r.name != null) { acc[key].vsx += r.wx; acc[key].vsy += r.wy; acc[key].vn++; }
      }
      for (const k in acc) {
        const a = acc[k];
        z.cxByZ[k] = a.vn ? { x: a.vsx / a.vn, y: a.vsy / a.vn, z: +k, seen: true }
          : { x: a.sx / a.n, y: a.sy / a.n, z: +k, seen: false };
      }
      cursorX += wCells + ZONE_GAP;
      rowH = Math.max(rowH, hCells);
    }

    const zset = new Set(world.rooms.map(r => r.z));
    zLevels = [...zset].sort((a, b) => a - b);
    if (!zLevels.includes(curZ)) curZ = zLevels[0] ?? 0;
  }

  // --- camera helpers ----------------------------------------------------
  const sx = wx => (wx - cam.x) * cam.scale + W / 2;
  const sy = wy => (wy - cam.y) * cam.scale + H / 2;
  const wox = px => (px - W / 2) / cam.scale + cam.x;
  const woy = py => (py - H / 2) / cam.scale + cam.y;

  function setScale(next, aroundX, aroundY) {
    next = Math.max(MIN_SCALE, Math.min(MAX_SCALE, next));
    if (next === cam.scale) return;
    const ax = aroundX == null ? W / 2 : aroundX;
    const ay = aroundY == null ? H / 2 : aroundY;
    const wx = wox(ax), wy = woy(ay);
    cam.scale = next;
    cam.x = wx - (ax - W / 2) / cam.scale;
    cam.y = wy - (ay - H / 2) / cam.scale;
    try { localStorage.setItem(LS_ZOOM, String(cam.scale)); } catch (e) {}
  }

  function centerOn(wx, wy, scale) {
    cam.x = wx; cam.y = wy;
    if (scale) { cam.scale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale)); try { localStorage.setItem(LS_ZOOM, String(cam.scale)); } catch (e) {} }
  }

  function focusVnum(vnum, scale) {
    const r = byVnum.get(vnum);
    if (!r) return;
    curZ = r.z;
    centerOn(r.wx, r.wy, scale || Math.max(cam.scale, 1.1));
    syncHud();
  }

  function recenterMe() {
    const me = byVnum.get(hereVnum);
    if (me) { curZ = me.z; centerOn(me.wx, me.wy, Math.max(cam.scale, 0.9)); }
    else if (world.rooms[0]) centerOn(world.rooms[0].wx, world.rooms[0].wy);
    syncHud();
  }

  // --- drawing ---------------------------------------------------------
  function resize() {
    dpr = window.devicePixelRatio || 1;
    W = container.clientWidth; H = container.clientHeight;
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function roomFill(r) {
    if (r.vnum === hereVnum) return '#ff2d55';
    const h = (r.zoneRef ? r.zoneRef.hue : 200);
    return `hsl(${h} 78% 62%)`;
  }

  function draw() {
    if (disposed) return;
    const now = performance.now();
    ctx.clearRect(0, 0, W, H);
    // ground
    ctx.fillStyle = '#05060c';
    ctx.fillRect(0, 0, W, H);

    // faint grid
    ctx.strokeStyle = 'rgba(80,110,180,0.05)';
    ctx.lineWidth = 1;
    const step = GRID * cam.scale;
    if (step > 8) {
      const x0 = ((W / 2 - cam.x * cam.scale) % step + step) % step;
      const y0 = ((H / 2 - cam.y * cam.scale) % step + step) % step;
      ctx.beginPath();
      for (let x = x0; x < W; x += step) { ctx.moveTo(x, 0); ctx.lineTo(x, H); }
      for (let y = y0; y < H; y += step) { ctx.moveTo(0, y); ctx.lineTo(W, y); }
      ctx.stroke();
    }

    const rooms = world.rooms.filter(r => r.z === curZ);
    const R = Math.max(3, 9 * cam.scale);

    // 1) exits under the nodes (visited rooms only)
    ctx.lineWidth = Math.max(1, 1.6 * cam.scale);
    for (const r of rooms) {
      if (r.name == null || !r.exits.length) continue;
      for (const ex of r.exits) {
        if (ex.dir === 'u' || ex.dir === 'd' || ex.dir === 'in' || ex.dir === 'out') continue;
        const t = byVnum.get(ex.to);
        if (!t || t.z !== curZ) continue;
        const ax = sx(r.wx), ay = sy(r.wy), bx = sx(t.wx), by = sy(t.wy);
        ctx.beginPath();
        if (ex.locked) {
          ctx.setLineDash([6, 5]); ctx.strokeStyle = '#ffb02e';
        } else {
          ctx.setLineDash([]);
          ctx.strokeStyle = `hsla(${r.zoneRef ? r.zoneRef.hue : 200} 60% 55% / 0.5)`;
        }
        ctx.moveTo(ax, ay); ctx.lineTo(bx, by); ctx.stroke();
      }
    }
    ctx.setLineDash([]);

    // 2) fog + ghost nodes
    for (const r of rooms) {
      if (r.name != null) continue;
      const x = sx(r.wx), y = sy(r.wy);
      if (x < -40 || x > W + 40 || y < -40 || y > H + 40) continue;
      const ghost = !!r.ghost;
      ctx.beginPath();
      ctx.arc(x, y, R * 0.7, 0, Math.PI * 2);
      ctx.fillStyle = ghost ? 'rgba(150,180,230,0.20)' : 'rgba(120,140,190,0.09)';
      ctx.fill();
      if (ghost) {
        ctx.strokeStyle = 'rgba(170,200,240,0.35)';
        ctx.lineWidth = 1; ctx.stroke();
      }
    }

    // 3) visited nodes
    for (const r of rooms) {
      if (r.name == null) continue;
      const x = sx(r.wx), y = sy(r.wy);
      if (x < -60 || x > W + 60 || y < -60 || y > H + 60) continue;
      const here = r.vnum === hereVnum;
      const pulse = here ? 0.5 + 0.5 * Math.sin((now - t0) / 260) : 0;
      ctx.save();
      ctx.shadowColor = roomFill(r);
      ctx.shadowBlur = here ? 14 + 10 * pulse : 8;
      ctx.fillStyle = roomFill(r);
      const s = R + (here ? 2 + 2 * pulse : 0);
      roundRect(ctx, x - s, y - s, s * 2, s * 2, Math.min(5, s * 0.4));
      ctx.fill();
      ctx.restore();

      if (r === selected) {
        ctx.strokeStyle = '#66e0ff';
        ctx.lineWidth = 2; ctx.strokeRect(x - s - 3, y - s - 3, (s + 3) * 2, (s + 3) * 2);
      }

      // up / down glyphs
      const hasUp = r.exits.some(e => e.dir === 'u');
      const hasDn = r.exits.some(e => e.dir === 'd');
      if ((hasUp || hasDn) && cam.scale > 0.4) {
        ctx.fillStyle = '#dfe6ff'; ctx.font = `${Math.round(9 * Math.min(1.6, cam.scale))}px ${MONO}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        if (hasUp && hasDn) ctx.fillText('⇅', x + s + 6, y);
        else if (hasUp) ctx.fillText('▲', x + s + 6, y);
        else ctx.fillText('▼', x + s + 6, y);
      }

      // markers
      if (cam.scale > 0.5) {
        const badges = [];
        if (r.shop) badges.push('¥');
        if (r.bank) badges.push('$');
        if (r.board) badges.push('▤');
        if (r.safe) badges.push('⛨');
        if (badges.length) {
          ctx.font = `${Math.round(10 * Math.min(1.5, cam.scale))}px ${MONO}`;
          ctx.textAlign = 'left'; ctx.textBaseline = 'bottom';
          ctx.fillStyle = '#0b0c15';
          ctx.fillText(badges.join(''), x - s + 0.5, y - s + 0.5);
          ctx.fillStyle = '#ffcf4a';
          ctx.fillText(badges.join(''), x - s, y - s);
        }
      }

      // label
      if (here || r === hover || cam.scale >= LABEL_SCALE) {
        const label = r.name;
        ctx.font = `${here ? 'bold ' : ''}11px ${MONO}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        const tw = ctx.measureText(label).width;
        ctx.fillStyle = 'rgba(5,6,12,0.78)';
        ctx.fillRect(x - tw / 2 - 4, y + s + 3, tw + 8, 15);
        ctx.fillStyle = here ? '#ff6a88' : '#c7d0f0';
        ctx.fillText(label, x, y + s + 5);
      }
    }

    // vignette over the unexplored dark
    const g = ctx.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.35, W / 2, H / 2, Math.max(W, H) * 0.75);
    g.addColorStop(0, 'rgba(0,0,0,0)');
    g.addColorStop(1, 'rgba(0,0,0,0.45)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    raf = requestAnimationFrame(draw);
  }

  function roundRect(c, x, y, w, h, r) {
    c.beginPath();
    c.moveTo(x + r, y);
    c.arcTo(x + w, y, x + w, y + h, r);
    c.arcTo(x + w, y + h, x, y + h, r);
    c.arcTo(x, y + h, x, y, r);
    c.arcTo(x, y, x + w, y, r);
    c.closePath();
  }

  // --- hit testing ----------------------------------------------------
  function roomAt(px, py) {
    let best = null, bd = 18 * 18;
    for (const r of world.rooms) {
      if (r.z !== curZ) continue;
      const dx = sx(r.wx) - px, dy = sy(r.wy) - py;
      const d = dx * dx + dy * dy;
      if (d < bd) { bd = d; best = r; }
    }
    return best;
  }

  // --- HUD / zone index / inspect ------------------------------------
  function syncHud() {
    const zi = zLevels.indexOf(curZ);
    // zone containing the camera centre
    let near = null, nd = Infinity;
    for (const z of zones) {
      const c = z.cxByZ[curZ];
      if (!c) continue;
      const d = (c.x - cam.x) ** 2 + (c.y - cam.y) ** 2;
      if (d < nd) { nd = d; near = z; }
    }
    hud.innerHTML = `LEVEL <b>${curZ}</b> <span>(${zi + 1}/${zLevels.length})</span>`
      + (near ? ` &nbsp;·&nbsp; <b style="color:hsl(${near.hue} 80% 68%)">${escapeHtml(near.name)}</b>` : '');
  }

  function buildZoneIndex() {
    zonesList.innerHTML = '';
    for (const z of zones) {
      const seen = z.rooms.filter(r => r.name != null).length;
      const row = document.createElement('button');
      row.className = 'wm-zrow';
      row.innerHTML = `<i style="background:hsl(${z.hue} 80% 62%)"></i>`
        + `<span>${escapeHtml(z.name)}</span><em>${seen}/${z.rooms.length}</em>`;
      row.onclick = () => {
        // pick the z-level of this zone with the most visited rooms, else any
        let pick = null, pn = -1;
        for (const k in z.cxByZ) {
          const cnt = z.rooms.filter(r => r.z === +k && r.name != null).length
            || (z.cxByZ[k].seen ? 1 : 0);
          if (cnt > pn) { pn = cnt; pick = z.cxByZ[k]; }
        }
        if (!pick) return;
        curZ = pick.z;
        centerOn(pick.x, pick.y, Math.max(cam.scale, 0.7));
        syncHud();
      };
      zonesList.appendChild(row);
    }
  }

  function openInspect(r) {
    selected = r;
    inspect.hidden = false;
    const exRows = r.exits.map(e => {
      const t = byVnum.get(e.to);
      const dest = t && t.name != null ? t.name : '???';
      const tag = e.locked ? ' \u{1F512}' : '';
      return `<li><b>${e.dir.toUpperCase()}</b>${tag} <span>${escapeHtml(dest)}</span></li>`;
    }).join('') || '<li class="none">no known exits</li>';
    inspect.innerHTML = `
      <button class="wm-x" title="Close">&times;</button>
      <div class="wm-i-name">${escapeHtml(r.name)}</div>
      <div class="wm-i-zone" style="color:hsl(${r.zoneRef.hue} 80% 68%)">${escapeHtml(r.zoneRef.name)} &middot; level ${r.z}
        ${r.vnum === hereVnum ? '&nbsp;<span class="wm-here">YOU ARE HERE</span>' : ''}</div>
      <canvas class="wm-inset" width="270" height="200"></canvas>
      <div class="wm-i-badges">${
        [r.shop && '¥ shop', r.bank && '$ bank', r.board && '▤ board', r.safe && '⛨ safe']
          .filter(Boolean).map(s => `<span>${s}</span>`).join('') || ''}</div>
      <div class="wm-i-hd">Exits</div>
      <ul class="wm-i-exits">${exRows}</ul>`;
    inspect.querySelector('.wm-x').onclick = () => { inspect.hidden = true; selected = null; };
    drawInset(inspect.querySelector('.wm-inset'), r);
  }

  function drawInset(c, r) {
    const ic = c.getContext('2d');
    const iw = c.width, ih = c.height;
    ic.fillStyle = '#05060c'; ic.fillRect(0, 0, iw, ih);
    const neigh = [];
    for (const e of r.exits) {
      const t = byVnum.get(e.to);
      if (t) neigh.push({ room: t, ex: e });
    }
    const cell = 62;
    const cx = iw / 2, cy = ih / 2;
    const px = rr => cx + (rr.x - r.x) * cell;
    const py = rr => cy - (rr.y - r.y) * cell;
    ic.strokeStyle = '#2b3352'; ic.lineWidth = 2;
    for (const n of neigh) {
      if (n.ex.dir === 'u' || n.ex.dir === 'd' || n.ex.dir === 'in' || n.ex.dir === 'out') continue;
      if (n.room.z !== r.z) continue;
      ic.beginPath(); ic.moveTo(cx, cy); ic.lineTo(px(n.room), py(n.room)); ic.stroke();
    }
    const node = (rr, big) => {
      const known = rr.name != null;
      const x = px(rr), y = py(rr), s = big ? 15 : 12;
      ic.fillStyle = rr.vnum === hereVnum ? '#ff2d55'
        : known ? `hsl(${(rr.zoneRef ? rr.zoneRef.hue : 200)} 78% 60%)` : 'rgba(150,170,220,0.22)';
      roundRect(ic, x - s, y - s, s * 2, s * 2, 4); ic.fill();
      ic.fillStyle = '#c7d0f0'; ic.font = `10px ${MONO}`; ic.textAlign = 'center'; ic.textBaseline = 'top';
      const nm = known ? rr.name : '???';
      ic.fillText(nm.length > 16 ? nm.slice(0, 15) + '…' : nm, x, y + s + 3);
    };
    for (const n of neigh) if (Math.abs(n.room.x - r.x) <= 1 && Math.abs(n.room.y - r.y) <= 1) node(n.room, false);
    node(r, true);
    c.onclick = ev => {
      const rect = c.getBoundingClientRect();
      const mx = (ev.clientX - rect.left) * (iw / rect.width);
      const my = (ev.clientY - rect.top) * (ih / rect.height);
      for (const n of neigh) {
        if (Math.abs(px(n.room) - mx) < 18 && Math.abs(py(n.room) - my) < 18) {
          focusVnum(n.room.vnum);
          if (n.room.name != null) openInspect(n.room);
          return;
        }
      }
    };
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  // --- events -------------------------------------------------------
  const ro = new ResizeObserver(() => { resize(); });
  ro.observe(container);

  function onWheel(e) {
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const factor = Math.exp(-e.deltaY * 0.0015);
    setScale(cam.scale * factor, e.clientX - rect.left, e.clientY - rect.top);
  }
  function onDown(e) {
    dragging = true; dragMoved = false;
    lastPt = { x: e.clientX, y: e.clientY };
    canvas.classList.add('drag');
    canvas.setPointerCapture && e.pointerId != null && canvas.setPointerCapture(e.pointerId);
  }
  function onMove(e) {
    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left, my = e.clientY - rect.top;
    if (dragging && lastPt) {
      const ddx = e.clientX - lastPt.x, ddy = e.clientY - lastPt.y;
      if (Math.abs(ddx) + Math.abs(ddy) > 3) dragMoved = true;
      cam.x -= ddx / cam.scale;
      cam.y -= ddy / cam.scale;
      lastPt = { x: e.clientX, y: e.clientY };
      syncHud();
    } else {
      hover = roomAt(mx, my);
      canvas.style.cursor = hover ? 'pointer' : 'grab';
    }
  }
  function onUp(e) {
    if (dragging && !dragMoved) {
      const rect = canvas.getBoundingClientRect();
      const r = roomAt(e.clientX - rect.left, e.clientY - rect.top);
      if (r && r.name != null) openInspect(r);
      else if (r) { selected = null; inspect.hidden = true; }
    }
    dragging = false; lastPt = null;
    canvas.classList.remove('drag');
  }

  // pinch zoom (bonus)
  let pinchD = 0;
  function touchDist(t) {
    const dx = t[0].clientX - t[1].clientX, dy = t[0].clientY - t[1].clientY;
    return Math.hypot(dx, dy);
  }
  function onTouchMove(e) {
    if (e.touches.length === 2) {
      e.preventDefault();
      const d = touchDist(e.touches);
      if (pinchD) {
        const rect = canvas.getBoundingClientRect();
        const mx = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
        const my = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;
        setScale(cam.scale * (d / pinchD), mx, my);
      }
      pinchD = d;
    }
  }
  function onTouchEnd() { pinchD = 0; }

  function onKey(e) {
    if (/input|textarea/i.test(e.target.tagName)) return;
    const k = e.key;
    if (k === '+' || k === '=') { setScale(cam.scale * 1.2); e.preventDefault(); }
    else if (k === '-' || k === '_') { setScale(cam.scale / 1.2); e.preventDefault(); }
    else if (k === '[') { const i = zLevels.indexOf(curZ); if (i > 0) { curZ = zLevels[i - 1]; syncHud(); } e.preventDefault(); }
    else if (k === ']') { const i = zLevels.indexOf(curZ); if (i < zLevels.length - 1) { curZ = zLevels[i + 1]; syncHud(); } e.preventDefault(); }
    else if (k === 'ArrowLeft') { cam.x -= 60 / cam.scale; e.preventDefault(); }
    else if (k === 'ArrowRight') { cam.x += 60 / cam.scale; e.preventDefault(); }
    else if (k === 'ArrowUp') { cam.y -= 60 / cam.scale; e.preventDefault(); }
    else if (k === 'ArrowDown') { cam.y += 60 / cam.scale; e.preventDefault(); }
    else return;
    syncHud();
  }

  container.querySelector('.wm-ctrls').addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    const a = b.dataset.act;
    if (a === 'in') setScale(cam.scale * 1.25);
    else if (a === 'out') setScale(cam.scale / 1.25);
    else if (a === 'me') recenterMe();
    else if (a === 'zup') { const i = zLevels.indexOf(curZ); if (i < zLevels.length - 1) curZ = zLevels[i + 1]; }
    else if (a === 'zdn') { const i = zLevels.indexOf(curZ); if (i > 0) curZ = zLevels[i - 1]; }
    else if (a === 'zones') zonesBox.classList.toggle('collapsed');
    syncHud();
  });
  container.querySelector('.wm-zones-hd').onclick = () => zonesBox.classList.toggle('collapsed');

  canvas.addEventListener('wheel', onWheel, { passive: false });
  canvas.addEventListener('pointerdown', onDown);
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  canvas.addEventListener('touchmove', onTouchMove, { passive: false });
  window.addEventListener('touchend', onTouchEnd);
  window.addEventListener('keydown', onKey, true);

  // --- boot -------------------------------------------------------
  resize();
  fetchWorld().then(m => {
    if (disposed) return;
    world = m;
    layout();
    buildZoneIndex();
    msg.remove();
    recenterMe();
    syncHud();
    raf = requestAnimationFrame(draw);
  }).catch(err => {
    if (disposed) return;
    msg.textContent = 'map offline: ' + (err && err.message ? err.message : 'error');
  });

  // --- cleanup --------------------------------------------------
  function cleanup() {
    if (disposed) return;
    disposed = true;
    cancelAnimationFrame(raf);
    ro.disconnect();
    canvas.removeEventListener('wheel', onWheel);
    canvas.removeEventListener('pointerdown', onDown);
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    canvas.removeEventListener('touchmove', onTouchMove);
    window.removeEventListener('touchend', onTouchEnd);
    window.removeEventListener('keydown', onKey, true);
    mo.disconnect();
  }

  // auto-cleanup if the modal is torn down without calling our cleanup
  const mo = new MutationObserver(() => {
    if (!container.isConnected) cleanup();
  });
  mo.observe(document.body, { childList: true, subtree: true });

  return cleanup;
}
