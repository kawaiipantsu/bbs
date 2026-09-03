/* chiptune.js - CHIPTUNE RADIO
 *
 * Streams real tracker modules (.mod / .xm / .s3m / .it) through libopenmpt
 * (WASM) via the vendored `chiptune3` AudioWorklet player in /js/vendor/.
 *
 * Output is routed through a dedicated GainNode (+ a soft limiter) into
 * `sound.master` - NOT `sound.musicBus` - so it plays independently of the
 * generative background bed. The parent calls `sound.stopMusic()` before
 * starting a track and resumes the bed via the `onEnd` callback / the stop
 * action (see NOTES-chiptune2.md for the app.js wiring).
 *
 * Playback model: a QUEUE of global catalogue indices. `playQueue(indices)`
 * walks them in order and, on a track's natural end, advances to the next -
 * wrapping around at the end so the queue loops forever. A track that fails to
 * load is skipped; `onEnd` fires only if the whole queue is unplayable.
 * `play(index)` is just `playQueue([index])` - a 1-track looping queue, which
 * matches the old "loop the single track" behaviour.
 *
 * Every public method is no-op-safe: if init failed, calls are swallowed and
 * logged, never thrown back at the caller. If init/worklet dies, play/playQueue
 * invoke onEnd() so the generative bed can resume.
 *
 * IMPORTANT: the audio.js import below MUST carry the same `?v=` query as the
 * rest of html/js/*.js, or `sound` resolves to a second, separate module
 * instance with its own (null) AudioContext. Parent bumps the pack to ?v=6.
 */

import { sound } from './audio.js?v=7';

const VENDOR_ENTRY = '/js/vendor/chiptune3.js';
const MANIFEST_URL = '/media/tracks/manifest.json';
const TRACK_BASE   = '/media/tracks/';
const INIT_TIMEOUT = 20000;   // 1.5 MB worklet + wasm compile on a cold cache
const META_TIMEOUT = 1800;    // wait for the worklet to ack a play() before retry

class Chiptune {
  constructor() {
    this._player  = null;    // ChiptuneJsPlayer
    this._ctx     = null;    // AudioContext (shared with `sound` when possible)
    this._out     = null;    // our volume GainNode -> sound.master
    this._ownsCtx = false;   // true if we had to create our own AudioContext

    this._initPromise = null;
    this._ready  = false;
    this._failed = false;

    this._manifestPromise = null;
    this._tracks  = [];      // catalogue: manifest.json .tracks / flattened categories / injected
    this._catalog = null;    // catalogue injected by the server (see setCatalog)

    this._queue   = [];      // list<int> of catalogue indices currently on rotation
    this._qpos    = 0;       // position within _queue
    this._idx     = -1;      // catalogue index of the track last handed to the worklet
    this._playing = false;
    this._vol     = 0.85;
    this._cbs     = null;    // { onStart, onEnd } for the active station run

    this._stopping   = false;
    this._advancing  = false;
    this._loadToken  = 0;    // guards against out-of-order async track loads
    this._failStreak = 0;    // consecutive load failures during auto-advance
    this._metaTimer  = null; // per-load "did the worklet start?" watchdog
    this._retry      = false;
  }

  // ---- init --------------------------------------------------------------

  async init() {
    if (this._ready)  return true;
    if (this._failed) return false;
    if (this._initPromise) return this._initPromise;
    this._initPromise = this._doInit().then(
      () => { this._ready = true; return true; },
      (err) => {
        console.warn('[chiptune] init failed:', (err && err.message) || err);
        this._failed = true;
        this._teardown();
        return false;
      },
    );
    return this._initPromise;
  }

  async _doInit() {
    // Prefer the BBS AudioContext so everything shares one clock / output.
    try { sound && typeof sound._ensure === 'function' && sound._ensure(); } catch (_) {}
    if (sound && sound.ctx) {
      this._ctx = sound.ctx;
      this._ownsCtx = false;
    } else {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) throw new Error('WebAudio unavailable');
      this._ctx = new AC();
      this._ownsCtx = true;
    }
    if (this._ctx.state === 'suspended') { try { await this._ctx.resume(); } catch (_) {} }

    // volume node (+ transparent limiter) -> master
    this._out = this._ctx.createGain();
    this._out.gain.value = this._vol;
    let tail = this._out;
    try {
      const lim = this._ctx.createDynamicsCompressor();
      lim.threshold.value = -3; lim.knee.value = 0; lim.ratio.value = 20;
      lim.attack.value = 0.003; lim.release.value = 0.18;
      this._out.connect(lim);
      tail = lim;
    } catch (_) { /* older engines: run without the limiter */ }
    const dest = (sound && sound.master) ? sound.master : this._ctx.destination;
    tail.connect(dest);

    // Load the vendored player (ES module). Its constructor kicks off
    // audioWorklet.addModule internally; we wait for its onInitialized event.
    const mod = await import(VENDOR_ENTRY);
    const ChiptuneJsPlayer = mod.ChiptuneJsPlayer || mod.default;
    if (typeof ChiptuneJsPlayer !== 'function') throw new Error('bad chiptune3 module');

    const player = new ChiptuneJsPlayer({
      context: this._ctx,
      repeatCount: 0,          // play once; we auto-advance the "station" on end
      stereoSeparation: 100,
      interpolationFilter: 0,
    });

    await new Promise((resolve, reject) => {
      const t = setTimeout(() => reject(new Error('worklet init timeout')), INIT_TIMEOUT);
      let done = false;
      const ok = () => { if (!done) { done = true; clearTimeout(t); resolve(); } };
      const no = (e) => {
        if (!done) { done = true; clearTimeout(t); reject(new Error('worklet error: ' + (e && e.type))); }
      };
      player.onInitialized(ok);
      player.onError(no);
    });

    // The player's internal gain (value 1) is created synchronously in its
    // constructor and left unconnected when we pass our own context; wire it
    // into our volume node. Re-assert once more shortly after in case the
    // worklet's async setup rebuilt the graph (connect() is idempotent).
    const wire = () => { try { player.gain && player.gain.connect(this._out); } catch (_) {} };
    wire();
    setTimeout(wire, 80);

    player.onMetadata((m) => this._onMeta(m));
    player.onEnded(() => this._onTrackEnded());
    player.onError((e) => this._onPlayerError(e));

    this._player = player;

    // The worklet still has to finish instantiating libopenmpt inside itself
    // after onInitialized fires; a short settle avoids the first play() racing
    // an undefined module. The META_TIMEOUT retry is the real safety net.
    await new Promise((r) => setTimeout(r, 250));
  }

  _teardown() {
    try { this._player && this._player.stop && this._player.stop(); } catch (_) {}
    try { this._out && this._out.disconnect(); } catch (_) {}
    if (this._ownsCtx) { try { this._ctx && this._ctx.close(); } catch (_) {} }
    this._player = null;
  }

  // ---- catalogue ------------------------------------------------------

  /**
   * Seed the track catalogue from the server (the ChiptuneModule frame carries
   * it in `meta.chiptuneCatalog`). This is the reliable path: the site's
   * .htaccess denies static *.json, so the fetch() in list() may 403 - the
   * injected catalogue then stands in. Safe to call repeatedly.
   *
   * Entries: { file, title, artist, format, cat }. `file` is required - the URL
   * is built as `/media/tracks/` + encodeURIComponent(file).
   */
  setCatalog(tracks) {
    if (!Array.isArray(tracks) || !tracks.length) return;
    this._catalog = tracks.slice();
    this._tracks = this._catalog;
  }

  async list() {
    if (this._catalog) return this._catalog;
    if (this._manifestPromise) return this._manifestPromise;
    this._manifestPromise = fetch(MANIFEST_URL, { credentials: 'same-origin' })
      .then((r) => { if (!r.ok) throw new Error('manifest HTTP ' + r.status); return r.json(); })
      .then((j) => {
        // new format: { categories: [ { id, name, tracks: [...] } ] }
        // old format: { tracks: [...] }
        let t = [];
        if (j && Array.isArray(j.categories)) {
          for (const c of j.categories) {
            if (!c || !Array.isArray(c.tracks)) continue;
            for (const tr of c.tracks) {
              if (tr && tr.file) t.push(Object.assign({ cat: c.id }, tr));
            }
          }
        } else if (j && Array.isArray(j.tracks)) {
          t = j.tracks;
        }
        if (t.length && !this._catalog) this._tracks = t;
        return this._tracks;
      })
      .catch((err) => {
        console.warn('[chiptune] manifest fetch failed (using injected catalogue):',
          (err && err.message) || err);
        return this._tracks;
      });
    return this._manifestPromise;
  }

  // ---- playback ------------------------------------------------------

  /**
   * Start a 1-track looping queue at catalogue index `index`.
   * Kept for API compatibility - it is exactly playQueue([index], opts).
   */
  async play(index, opts = {}) {
    return this.playQueue([index], opts);
  }

  /**
   * Start the station on a queue of global catalogue indices, played in order.
   * On a track's natural end the next one starts; the queue wraps at the end and
   * loops forever. A track that fails to load is skipped. `onStart(meta, qpos)`
   * fires on every track start (including auto-advances). `onEnd()` fires only
   * when the whole queue is unplayable (every entry failed) - the parent uses it
   * to bring the generative bed back.
   */
  async playQueue(indices, opts = {}) {
    try {
      const ok = await this.init();
      if (!ok) { this._safeEnd(opts.onEnd); return; }
      await this.list();
      if (!this._tracks.length) { this._safeEnd(opts.onEnd); return; }

      const q = this._normQueue(indices);
      if (!q.length) { this._safeEnd(opts.onEnd); return; }

      this._cbs = { onStart: opts.onStart || null, onEnd: opts.onEnd || null };
      this._queue = q;
      this._qpos = 0;
      this._failStreak = 0;
      await this._loadIndex(this._queue[0]);
    } catch (err) {
      console.warn('[chiptune] playQueue failed:', (err && err.message) || err);
      this._safeEnd(opts.onEnd);
    }
  }

  /** Coerce an arbitrary list into valid, in-range catalogue indices. */
  _normQueue(indices) {
    if (!Array.isArray(indices)) return [];
    const n = this._tracks.length;
    const out = [];
    for (const raw of indices) {
      const i = Math.trunc(Number(raw));
      if (Number.isFinite(i) && i >= 0 && i < n) out.push(i);
    }
    return out;
  }

  async _loadIndex(catIdx) {
    if (!this._player) return;
    const token = ++this._loadToken;
    this._clearMetaTimer();
    this._retry = false;
    this._idx = catIdx;

    const meta = this._tracks[catIdx];
    if (!meta || !meta.file) return this._advanceAfterFailure(token);

    let buf;
    try {
      const url = TRACK_BASE + encodeURIComponent(meta.file);
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      buf = await res.arrayBuffer();
    } catch (err) {
      console.warn('[chiptune] track fetch failed:', meta.file, (err && err.message) || err);
      return this._advanceAfterFailure(token);
    }
    if (token !== this._loadToken) return;   // superseded

    this._startWorklet(buf, token);
  }

  _startWorklet(buf, token) {
    if (token !== this._loadToken || !this._player) return;
    try {
      if (this._ctx && this._ctx.state === 'suspended') { this._ctx.resume().catch(() => {}); }
      this._stopping = true;
      try { this._player.stop(); } catch (_) {}
      this._stopping = false;
      this._player.play(buf);
    } catch (err) {
      console.warn('[chiptune] worklet play failed:', (err && err.message) || err);
      return this._advanceAfterFailure(token);
    }
    // Wait for onMetadata (worklet ack). If it never comes, retry once, then
    // give up on this track and roll forward.
    this._clearMetaTimer();
    this._metaTimer = setTimeout(() => {
      if (token !== this._loadToken) return;
      if (!this._retry) {
        this._retry = true;
        this._startWorklet(buf, token);
      } else {
        console.warn('[chiptune] worklet never acknowledged play; skipping track');
        this._advanceAfterFailure(token);
      }
    }, META_TIMEOUT);
  }

  _onMeta() {
    this._clearMetaTimer();
    this._retry = false;
    this._playing = true;
    this._failStreak = 0;
    this._emitStart(this._trackMeta(this._idx), this._qpos);
  }

  _onPlayerError(e) {
    console.warn('[chiptune] player error:', e && e.type);
    if (this._metaTimer) {                 // error during a pending load
      const token = this._loadToken;
      this._clearMetaTimer();
      this._advanceAfterFailure(token);
    }
  }

  /** Step the queue position by `delta` (wrapping) and return the catalogue idx. */
  _step(delta) {
    const qn = this._queue.length || 1;
    this._qpos = (((this._qpos + delta) % qn) + qn) % qn;
    const idx = this._queue[this._qpos];
    return (typeof idx === 'number') ? idx : 0;
  }

  _advanceAfterFailure(token) {
    if (token !== this._loadToken) return;
    this._failStreak++;
    if (this._failStreak >= (this._queue.length || 1)) {
      this._playing = false;
      this._failStreak = 0;
      this._safeEnd(this._cbs && this._cbs.onEnd);
      return;
    }
    return this._loadIndex(this._step(1));
  }

  _onTrackEnded() {
    if (this._stopping || this._advancing || !this._playing) return;
    // Natural end of a track. With repeatCount 0 the worklet spams 'end' every
    // audio quantum until we act, so latch immediately and roll forward once.
    this._playing = false;
    this._advancing = true;
    Promise.resolve()
      .then(() => this._loadIndex(this._step(1)))
      .catch((err) => console.warn('[chiptune] auto-advance failed:', (err && err.message) || err))
      .finally(() => { this._advancing = false; });
  }

  next() {
    if (!this._ready || !this._queue.length || this._advancing) return;
    this._advancing = true;
    Promise.resolve()
      .then(() => this._loadIndex(this._step(1)))
      .catch(() => {})
      .finally(() => { this._advancing = false; });
  }

  prev() {
    if (!this._ready || !this._queue.length || this._advancing) return;
    this._advancing = true;
    Promise.resolve()
      .then(() => this._loadIndex(this._step(-1)))
      .catch(() => {})
      .finally(() => { this._advancing = false; });
  }

  stop() {
    this._loadToken++;            // cancel any in-flight track load
    this._clearMetaTimer();
    this._playing = false;
    this._advancing = false;
    this._idx = -1;
    this._queue = [];
    this._qpos = 0;
    if (!this._player) return;
    try {
      this._stopping = true;
      this._player.stop();
    } catch (err) {
      console.warn('[chiptune] stop failed:', (err && err.message) || err);
    } finally {
      this._stopping = false;
    }
  }

  // ---- misc ----------------------------------------------------------

  setVolume(v) {
    const x = Math.max(0, Math.min(1, Number(v)));
    if (!Number.isFinite(x)) return;
    this._vol = x;
    try {
      if (this._out) this._out.gain.setTargetAtTime(x, this._ctx.currentTime, 0.02);
    } catch (_) {
      try { if (this._out) this._out.gain.value = x; } catch (__) {}
    }
  }

  isPlaying() { return !!this._playing; }

  nowPlaying() {
    return (this._playing && this._idx >= 0) ? this._trackMeta(this._idx) : null;
  }

  // ---- helpers -----------------------------------------------------

  _clearMetaTimer() {
    if (this._metaTimer) { clearTimeout(this._metaTimer); this._metaTimer = null; }
  }

  _trackMeta(idx) {
    const t = this._tracks[idx] || {};
    return {
      index: idx,
      qpos: this._qpos,
      file: t.file || '',
      title: t.title || t.file || 'unknown',
      artist: t.artist || '',
      format: t.format || '',
      cat: t.cat || '',
    };
  }

  _emitStart(meta, qpos) {
    try { this._cbs && this._cbs.onStart && this._cbs.onStart(meta, qpos); }
    catch (err) { console.warn('[chiptune] onStart threw:', (err && err.message) || err); }
  }

  _safeEnd(fn) {
    try { fn && fn(); }
    catch (err) { console.warn('[chiptune] onEnd threw:', (err && err.message) || err); }
  }
}

export const chiptune = new Chiptune();
export default chiptune;
