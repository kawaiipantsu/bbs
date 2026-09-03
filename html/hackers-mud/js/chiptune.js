/* chiptune.js - "chiptune radio" for the graphical client.
   Lazy-loads the vendored chiptune3 (libopenmpt WASM AudioWorklet) and plays a
   shuffled, endless rotation of the site's real tracker modules from
   /media/tracks/. Shares the MUD client's AudioContext; routes through its own
   GainNode into whatever destination node audio.js hands it (audio.musG).

   Every public method is no-op-safe and swallows + logs its own errors: a
   missing worklet, a blocked wasm compile, a 404 track, etc. must never break
   the game's audio. On hard init failure `failed` is set and stays set. */

const MANIFEST_URL = '/media/tracks/manifest.json';
const TRACK_BASE   = '/media/tracks/';
const VENDOR_URL   = '/js/vendor/chiptune3.js';

class Chiptune {
  constructor() {
    this.failed = false;
    this.player = null;
    this.gain = null;
    this._ctx = null;
    this._list = null;
    this._last = -1;
    this._want = false;        // rotation is meant to be running
    this._started = false;     // a track has actually been handed to the worklet
    this._advancing = false;   // guards the onEnded flood while we fetch the next
    this._now = null;
    this._onTrack = null;
    this._vol = 1;
    this._gen = 0;             // bumped on stop()/new pick so stale async work aborts
    this._initPromise = null;
  }

  /* lazy-import chiptune3, spin up the worklet on the shared ctx, wire
     player -> this.gain -> destNode. Safe to call repeatedly. */
  async init(ctx, destNode) {
    if (this.failed) return;
    if (this.player) return;
    if (this._initPromise) return this._initPromise;
    this._initPromise = (async () => {
      try {
        this._ctx = ctx;
        const mod = await import(VENDOR_URL);
        const Player = mod.ChiptuneJsPlayer || mod.default;
        if (!Player) throw new Error('ChiptuneJsPlayer export missing');
        const player = new Player({ context: ctx, repeatCount: 0, stereoSeparation: 100 });
        // wait for the worklet module to compile + the processor node to exist,
        // otherwise the first play() is silently dropped by postMsg().
        await new Promise((resolve) => {
          let done = false;
          const finish = () => { if (!done) { done = true; resolve(); } };
          try { player.addHandler('onInitialized', finish); } catch (_) {}
          setTimeout(finish, 5000);
        });
        this.gain = ctx.createGain();
        this.gain.gain.value = this._vol;
        try { player.gain.connect(this.gain); } catch (_) {}
        this.gain.connect(destNode || ctx.destination);
        try {
          player.addHandler('onEnded', () => this._onEnded());
          player.addHandler('onError', (e) => console.warn('[chiptune] player error', e));
        } catch (_) {}
        this.player = player;
      } catch (e) {
        console.warn('[chiptune] init failed', e);
        this.failed = true;
      }
    })();
    return this._initPromise;
  }

  /* fetch + flatten the manifest to one [{file,title,artist,format}] array. */
  async loadList() {
    if (this._list) return this._list;
    try {
      const r = await fetch(MANIFEST_URL, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('manifest HTTP ' + r.status);
      const j = await r.json();
      const out = [];
      for (const cat of (j && j.categories) || []) {
        for (const t of (cat && cat.tracks) || []) {
          if (t && t.file) out.push({
            file: String(t.file),
            title: t.title || String(t.file),
            artist: t.artist || '',
            format: t.format || '',
          });
        }
      }
      this._list = out;
    } catch (e) {
      console.warn('[chiptune] loadList failed', e);
      this._list = [];
    }
    return this._list;
  }

  /* pick a uniformly-random track (never the one just played), fetch it as an
     ArrayBuffer, hand it to the worklet, start. onTrack(meta) fires on start.
     Natural end -> onEnded -> playRandom() again (endless shuffle). */
  async playRandom(opts) {
    opts = opts || {};
    if (opts.onTrack) this._onTrack = opts.onTrack;
    this._want = true;
    if (this.failed || !this.player) return;
    const gen = ++this._gen;
    try {
      const list = await this.loadList();
      if (!list.length) { console.warn('[chiptune] no tracks in manifest'); return; }
      let idx = (Math.random() * list.length) | 0;
      if (list.length > 1 && idx === this._last) {
        idx = (idx + 1 + ((Math.random() * (list.length - 1)) | 0)) % list.length;
      }
      const meta = list[idx];
      const r = await fetch(TRACK_BASE + encodeURIComponent(meta.file), { credentials: 'same-origin' });
      if (!r.ok) throw new Error('track HTTP ' + r.status + ' for ' + meta.file);
      const buf = await r.arrayBuffer();
      if (gen !== this._gen || !this.player || !this._want) return;   // superseded / stopped
      this._last = idx;
      this.player.play(buf);
      this._started = true;
      this._advancing = false;
      this._now = { ...meta };
      try { this._onTrack && this._onTrack({ ...meta }); } catch (_) {}
    } catch (e) {
      console.warn('[chiptune] playRandom failed', e);
      this._advancing = false;
      // one delayed retry, only while the rotation is still wanted
      setTimeout(() => {
        if (this._want && gen === this._gen && this.player && !this.failed) this.playRandom();
      }, 5000);
    }
  }

  _onEnded() {
    if (!this._want || this._advancing || !this._started) return;
    this._advancing = true;
    try { this.player && this.player.stop(); } catch (_) {}
    this.playRandom();
  }

  stop() {
    this._want = false;
    this._started = false;
    this._advancing = false;
    this._now = null;
    this._gen++;
    try { this.player && this.player.stop(); } catch (_) {}
  }

  setVolume(v) {
    v = Math.max(0, Math.min(1, Number(v) || 0));
    this._vol = v;
    try {
      if (this.gain && this._ctx) this.gain.gain.setTargetAtTime(v, this._ctx.currentTime, 0.05);
      else if (this.gain) this.gain.gain.value = v;
    } catch (_) {}
  }

  isPlaying() { return !!this._want; }
  nowPlaying() { return this._now ? { ...this._now } : null; }
}

export const chiptune = new Chiptune();
