/* audio.js - everything you hear is synthesised here with WebAudio.
   No sample files. Key clicks, the BBS bell, and a scripted modem handshake
   that "dials" the caller's phone number in DTMF before the carrier locks. */

const DTMF = {
  '1': [697, 1209], '2': [697, 1336], '3': [697, 1477],
  '4': [770, 1209], '5': [770, 1336], '6': [770, 1477],
  '7': [852, 1209], '8': [852, 1336], '9': [852, 1477],
  '*': [941, 1209], '0': [941, 1336], '#': [941, 1477],
};

export class Sound {
  constructor() {
    this.ctx = null;
    this.enabled = false;
    this.master = null;
    this._unlocked = false;
  }

  _ensure() {
    if (this.ctx) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    this.ctx = new AC();
    this.master = this.ctx.createGain();
    this.master.gain.value = 0.5;
    this.master.connect(this.ctx.destination);
    // music sits on its own low bus, well under the modem + UI sounds, with a
    // transparent limiter so a scheduler hitch can never turn into a spike.
    this.musicBus = this.ctx.createGain();
    this.musicBus.gain.value = 0.55;
    try {
      const lim = this.ctx.createDynamicsCompressor();
      lim.threshold.value = -4; lim.knee.value = 0; lim.ratio.value = 20;
      lim.attack.value = 0.003; lim.release.value = 0.15;
      this.musicBus.connect(lim); lim.connect(this.master);
    } catch (_) { this.musicBus.connect(this.master); }
    // iOS re-suspends the context whenever it feels like it - chase it back.
    this.ctx.onstatechange = () => {
      if (this._unlocked && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    };
  }

  /**
   * Call from a user gesture to satisfy autoplay policy. iOS Safari needs
   * more hand-holding than desktop: resume the context AND play a one-sample
   * silent buffer inside the gesture, and prime a muted <audio> element so
   * WebAudio is routed through the path that ignores the ring/silent switch.
   */
  unlock() {
    this._ensure();
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    try {
      const b = this.ctx.createBuffer(1, 1, 22050);
      const s = this.ctx.createBufferSource();
      s.buffer = b;
      s.connect(this.ctx.destination);
      s.start(0);
    } catch (_) {}
    this._primeMediaEl();
    this._unlocked = true;
  }

  /** A silent looping <audio> element - on iOS this lifts the mute-switch
      silencing for the whole page's audio, WebAudio included. */
  _primeMediaEl() {
    if (this._mediaPrimed) return;
    try {
      const el = document.createElement('audio');
      el.setAttribute('playsinline', '');
      el.loop = true;
      el.preload = 'auto';
      // 0.05s of silent WAV as a data URI
      el.src = 'data:audio/wav;base64,UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAAAAAAAAAAAAAAAAAAAAAA=';
      el.volume = 0.001;
      const p = el.play();
      if (p && p.catch) p.catch(() => {});
      this._mediaEl = el;
      this._mediaPrimed = true;
    } catch (_) {}
  }

  setEnabled(on) {
    this.enabled = !!on;
    if (on) this.unlock();
    else { this.ambientStop(); this.stopMusic(0.3); }
  }

  /** Cheap idempotent nudge - call on every keydown/click. */
  resume() {
    if (this.ctx && this._unlocked && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
  }

  get ready() { return this.enabled && this.ctx && this._unlocked; }

  _tone(freq, t0, dur, { type = 'sine', gain = 0.2, glide = null } = {}) {
    const o = this.ctx.createOscillator();
    const g = this.ctx.createGain();
    o.type = type;
    o.frequency.setValueAtTime(freq, t0);
    if (glide) o.frequency.exponentialRampToValueAtTime(glide, t0 + dur);
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(gain, t0 + 0.008);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    o.connect(g).connect(this.master);
    o.start(t0);
    o.stop(t0 + dur + 0.02);
    return o;
  }

  _noise(t0, dur, { gain = 0.15, band = null, q = 1 } = {}) {
    const n = Math.max(1, Math.floor(this.ctx.sampleRate * dur));
    const buf = this.ctx.createBuffer(1, n, this.ctx.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < n; i++) d[i] = Math.random() * 2 - 1;
    const src = this.ctx.createBufferSource();
    src.buffer = buf;
    const g = this.ctx.createGain();
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(gain, t0 + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    let node = src;
    if (band) {
      const f = this.ctx.createBiquadFilter();
      f.type = 'bandpass';
      f.frequency.value = band;
      f.Q.value = q;
      node.connect(f);
      node = f;
    }
    node.connect(g).connect(this.master);
    src.start(t0);
    src.stop(t0 + dur + 0.02);
  }

  /* ---- UI sounds ------------------------------------------------- */
  key() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._noise(t, 0.028, { gain: 0.05, band: 2400, q: 0.7 });
    this._tone(160 + Math.random() * 40, t, 0.03, { type: 'square', gain: 0.03 });
  }

  move() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(880, t, 0.02, { type: 'sine', gain: 0.03 });
  }

  beep() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(1040, t, 0.08, { type: 'square', gain: 0.08 });
  }

  bell() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(1760, t, 0.5, { type: 'sine', gain: 0.09, glide: 1200 });
  }

  error() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(320, t, 0.12, { type: 'sawtooth', gain: 0.08 });
    this._tone(220, t + 0.1, 0.16, { type: 'sawtooth', gain: 0.08 });
  }

  connectChime() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    [523, 659, 784, 1046].forEach((f, i) => this._tone(f, t + i * 0.06, 0.14, { type: 'triangle', gain: 0.07 }));
  }

  hangup() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    // off-hook clunk + dial tone tail
    this._noise(t, 0.05, { gain: 0.12, band: 500 });
    this._tone(480, t + 0.08, 0.5, { gain: 0.05 });
    this._tone(620, t + 0.08, 0.5, { gain: 0.05 });
  }

  /* CRT power switch: a heavy relay clunk, a degauss "boing", flyback whine. */
  powerOn() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._noise(t, 0.04, { gain: 0.22, band: 220, q: 0.6 });          // clunk
    this._tone(60, t + 0.02, 0.18, { type: 'sine', gain: 0.14, glide: 40 });
    this._tone(90, t + 0.05, 0.5, { type: 'sine', gain: 0.10, glide: 200 }); // degauss boing
    this._tone(15734, t + 0.1, 1.6, { type: 'sine', gain: 0.012 });   // flyback whine ~15.7kHz
    this._noise(t + 0.1, 0.25, { gain: 0.03, band: 3000, q: 0.3 });
  }

  powerOff() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._noise(t, 0.035, { gain: 0.2, band: 200, q: 0.6 });          // clunk
    this._tone(15734, t, 0.25, { type: 'sine', gain: 0.012, glide: 400 }); // whine dies
    this._tone(220, t + 0.02, 0.22, { type: 'sine', gain: 0.08, glide: 30 });
  }

  tick() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(2600, t, 0.012, { type: 'square', gain: 0.03 });
  }

  /* NANP busy / engaged signal: 480 + 620 Hz, 0.5s on / 0.5s off, looping. */
  busySignal() {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    this._tone(480, t, 0.5, { gain: 0.10 });
    this._tone(620, t, 0.5, { gain: 0.10 });
  }

  startBusy() {
    this.stopBusy();
    this._ensure();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
    const cycle = () => { this.busySignal(); this._busy = setTimeout(cycle, 1000); };
    cycle();
  }

  stopBusy() {
    if (this._busy) { clearTimeout(this._busy); this._busy = null; }
  }

  /* ---- error tones: one looping "modem data" bed per failure type -----
     Each connection error gets its own recognisable sound that loops until
     the next screen renders or the caller hits a key. */

  /** One iteration of the bed for `type`. Returns the loop period in ms. */
  _errorBurst(type) {
    const t = this.ctx.currentTime;
    switch (type) {
      case 'carrier': {
        // CARRIER LOST - the carrier drops and the modem retrains: a
        // descending howl over a wash of noise, again and again.
        this._tone(1600, t, 0.5, { type: 'sawtooth', gain: 0.06, glide: 240 });
        this._tone(1100, t + 0.05, 0.5, { type: 'square', gain: 0.03, glide: 180 });
        this._noise(t, 0.6, { gain: 0.09, band: 1400, q: 0.4 });
        this._noise(t + 0.6, 0.3, { gain: 0.05, band: 600, q: 0.3 });
        return 1400;
      }
      case 'nocarrier': {
        // NO CARRIER - the three-tone Special Information Tone, looping.
        [914, 1372, 1777].forEach((f, i) =>
          this._tone(f, t + i * 0.33, 0.3, { type: 'sine', gain: 0.09 }));
        this._noise(t + 1.0, 0.15, { gain: 0.03, band: 1000 });
        return 1800;
      }
      case 'stale': {
        // stale token / desync - a corrupted packet stream: fast FSK-ish
        // chirps and bandpassed hash.
        for (let i = 0; i < 5; i++) {
          const f = 1000 + Math.random() * 1600;
          this._tone(f, t + i * 0.08, 0.06, { type: 'square', gain: 0.04 });
          this._noise(t + i * 0.08, 0.05, { gain: 0.05, band: 900 + Math.random() * 1800, q: 0.6 });
        }
        return 900;
      }
      default: {
        // generic rejection - a short, low, unhappy buzz. Least alarming.
        this._tone(200, t, 0.14, { type: 'square', gain: 0.06 });
        this._tone(150, t + 0.12, 0.16, { type: 'square', gain: 0.05 });
        this._noise(t, 0.06, { gain: 0.03, band: 400 });
        return 1200;
      }
    }
  }

  /** Start (or switch) the looping error bed for `type`. */
  errorLoop(type = 'reject') {
    this.stopErrorLoop();
    if (!this.ready) return;
    this._errType = type;
    this._errStarted = Date.now();
    const cycle = () => {
      // give up after ~20s so a walked-away tab goes quiet
      if (!this.ready || Date.now() - this._errStarted > 20000) { this.stopErrorLoop(); return; }
      const period = this._errorBurst(type) || 1400;
      this._errLoop = setTimeout(cycle, period);
    };
    cycle();
  }

  stopErrorLoop() {
    if (this._errLoop) { clearTimeout(this._errLoop); this._errLoop = null; }
    this._errType = null;
  }

  /* ---- Hackers-MUD: one-shot effect sounds ----------------------- */

  /** Dispatch a named MUD effect. Unknown names are ignored. */
  mud(name) {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    switch (name) {
      case 'step': case 'step2': {
        const lo = name === 'step' ? 90 : 70;
        this._noise(t, 0.06, { gain: 0.06, band: 260 + Math.random() * 60, q: 1.2 });
        this._tone(lo + Math.random() * 20, t, 0.05, { type: 'sine', gain: 0.05, glide: 45 });
        break;
      }
      case 'door':
        this._noise(t, 0.05, { gain: 0.16, band: 180, q: 0.6 });
        this._tone(140, t + 0.03, 0.12, { type: 'square', gain: 0.06, glide: 70 });
        this._tone(1200, t + 0.02, 0.04, { type: 'square', gain: 0.03 });
        break;
      case 'swing': case 'miss':
        this._noise(t, 0.14, { gain: 0.07, band: 1700, q: 0.4 });
        this._tone(500, t, 0.12, { type: 'sine', gain: 0.03, glide: 1600 });
        break;
      case 'blade':
        this._tone(1800, t, 0.14, { type: 'sawtooth', gain: 0.05, glide: 400 });
        this._noise(t, 0.1, { gain: 0.05, band: 3200, q: 0.5 });
        break;
      case 'hit':
        this._noise(t, 0.09, { gain: 0.13, band: 380, q: 0.8 });
        this._tone(120, t, 0.1, { type: 'square', gain: 0.08, glide: 60 });
        break;
      case 'crit':
        this._noise(t, 0.16, { gain: 0.2, band: 300, q: 0.7 });
        this._tone(90, t, 0.22, { type: 'square', gain: 0.12, glide: 40 });
        this._tone(240, t + 0.02, 0.18, { type: 'sawtooth', gain: 0.06, glide: 80 });
        break;
      case 'gun':
        this._noise(t, 0.05, { gain: 0.22, band: 900, q: 0.3 });
        this._noise(t, 0.18, { gain: 0.08, band: 220, q: 0.4 });
        this._tone(70, t, 0.14, { type: 'square', gain: 0.1, glide: 40 });
        break;
      case 'enemyhit':
        this._noise(t, 0.08, { gain: 0.09, band: 500, q: 0.9 });
        this._tone(200, t, 0.09, { type: 'sawtooth', gain: 0.05, glide: 110 });
        break;
      case 'hurt':
        this._tone(300, t, 0.16, { type: 'sawtooth', gain: 0.08, glide: 140 });
        this._noise(t, 0.1, { gain: 0.05, band: 700 });
        break;
      case 'kill':
        this._tone(180, t, 0.28, { type: 'square', gain: 0.09, glide: 50 });
        this._noise(t + 0.02, 0.18, { gain: 0.08, band: 260, q: 0.5 });
        break;
      case 'aggro':
        this._tone(140, t, 0.3, { type: 'sawtooth', gain: 0.07, glide: 220 });
        this._tone(90, t + 0.05, 0.3, { type: 'square', gain: 0.05, glide: 130 });
        break;
      case 'coin': case 'buy': case 'sell':
        this._tone(1660, t, 0.06, { type: 'square', gain: 0.06 });
        this._tone(2100, t + 0.05, 0.08, { type: 'square', gain: 0.06 });
        if (name !== 'sell') this._tone(2640, t + 0.11, 0.1, { type: 'triangle', gain: 0.05 });
        break;
      case 'pickup':
        this._tone(880, t, 0.04, { type: 'square', gain: 0.05 });
        this._tone(1320, t + 0.04, 0.05, { type: 'square', gain: 0.045 });
        break;
      case 'drink':
        this._noise(t, 0.22, { gain: 0.05, band: 600, q: 1.5 });
        this._tone(400, t, 0.2, { type: 'sine', gain: 0.03, glide: 520 });
        break;
      case 'eat':
        this._noise(t, 0.08, { gain: 0.07, band: 900, q: 1 });
        this._noise(t + 0.12, 0.08, { gain: 0.06, band: 700, q: 1 });
        break;
      case 'equip':
        this._noise(t, 0.05, { gain: 0.08, band: 2200, q: 0.6 });
        this._tone(600, t, 0.06, { type: 'square', gain: 0.04, glide: 300 });
        break;
      case 'hack':
        for (let i = 0; i < 4; i++) {
          this._tone(1400 + Math.random() * 900, t + i * 0.05, 0.04, { type: 'square', gain: 0.035 });
        }
        break;
      case 'hackok':
        [660, 880, 1320].forEach((f, i) => this._tone(f, t + i * 0.05, 0.09, { type: 'triangle', gain: 0.05 }));
        break;
      case 'hackfail':
        this._tone(200, t, 0.14, { type: 'sawtooth', gain: 0.07 });
        this._tone(150, t + 0.12, 0.16, { type: 'sawtooth', gain: 0.06 });
        break;
      case 'levelup':
        [523, 659, 784, 1046, 1318].forEach((f, i) =>
          this._tone(f, t + i * 0.07, 0.16, { type: 'triangle', gain: 0.06 }));
        break;
      case 'quest':
        [784, 1046, 1568].forEach((f, i) => this._tone(f, t + i * 0.08, 0.14, { type: 'triangle', gain: 0.055 }));
        break;
      case 'death':
        this._tone(400, t, 0.5, { type: 'sine', gain: 0.09, glide: 120 });
        this._tone(402, t, 0.5, { type: 'sine', gain: 0.06 });
        this._noise(t + 0.4, 0.5, { gain: 0.04, band: 400, q: 0.3 });
        break;
      case 'trace':
        for (let i = 0; i < 6; i++) this._tone(1800, t + i * 0.14, 0.06, { type: 'square', gain: 0.05 });
        break;
      default:
        break;
    }
  }

  /* ---- Hackers-MUD: looping ambient bed per zone ----------------- */

  /** Start or cross-fade to an ambient bed. key: rain|hum|drip|wind|static|room */
  ambient(key) {
    if (!this.enabled) { this._ambientKey = key; return; }
    this._ensure();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    if (this._ambientKey === key && this._ambientNodes) return;
    this.ambientStop(0.6);
    this._ambientKey = key;
    if (!this.ctx) return;

    const out = this.ctx.createGain();
    out.gain.setValueAtTime(0.0001, this.ctx.currentTime);
    out.gain.exponentialRampToValueAtTime(0.9, this.ctx.currentTime + 0.8);
    out.connect(this.master);

    // steady filtered-noise floor
    const n = this.ctx.createBufferSource();
    const len = Math.floor(this.ctx.sampleRate * 2);
    const buf = this.ctx.createBuffer(1, len, this.ctx.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    n.buffer = buf; n.loop = true;
    const f = this.ctx.createBiquadFilter();
    const g = this.ctx.createGain();

    const cfg = {
      rain:   { type: 'bandpass', freq: 1600, q: 0.4, gain: 0.03 },
      hum:    { type: 'lowpass',  freq: 180,  q: 0.7, gain: 0.05 },
      drip:   { type: 'lowpass',  freq: 500,  q: 0.6, gain: 0.02 },
      wind:   { type: 'bandpass', freq: 500,  q: 0.3, gain: 0.035 },
      static: { type: 'highpass', freq: 2200, q: 0.4, gain: 0.02 },
      room:   { type: 'lowpass',  freq: 320,  q: 0.6, gain: 0.02 },
    }[key] || { type: 'lowpass', freq: 400, q: 0.5, gain: 0.02 };

    f.type = cfg.type; f.frequency.value = cfg.freq; f.Q.value = cfg.q;
    g.gain.value = cfg.gain;
    n.connect(f).connect(g).connect(out);
    n.start();

    this._ambientNodes = { out, n };

    // sparse motif on top (drips, gusts, distant traffic booms)
    const motif = () => {
      if (!this._ambientNodes || this._ambientKey !== key) return;
      const tt = this.ctx.currentTime;
      if (key === 'drip') this._tone(900 + Math.random() * 400, tt, 0.05, { type: 'sine', gain: 0.03, glide: 300 });
      else if (key === 'wind') this._noise(tt, 0.9, { gain: 0.03, band: 300 + Math.random() * 300, q: 0.2 });
      else if (key === 'rain') this._noise(tt, 0.4, { gain: 0.015, band: 4000, q: 0.5 });
      else if (key === 'hum' || key === 'room') this._tone(55, tt, 1.2, { type: 'sine', gain: 0.02 });
      else if (key === 'static') for (let i = 0; i < 3; i++) this._tone(2000 + Math.random() * 2000, tt + i * 0.1, 0.05, { type: 'square', gain: 0.015 });
      this._ambientTimer = setTimeout(motif, 1400 + Math.random() * 2600);
    };
    this._ambientTimer = setTimeout(motif, 900);
  }

  ambientStop(fade = 0.4) {
    if (this._ambientTimer) { clearTimeout(this._ambientTimer); this._ambientTimer = null; }
    const nodes = this._ambientNodes;
    this._ambientNodes = null;
    this._ambientKey = null;
    if (!nodes || !this.ctx) return;
    try {
      nodes.out.gain.cancelScheduledValues(this.ctx.currentTime);
      nodes.out.gain.setValueAtTime(Math.max(0.0001, nodes.out.gain.value), this.ctx.currentTime);
      nodes.out.gain.exponentialRampToValueAtTime(0.0001, this.ctx.currentTime + fade);
      nodes.n.stop(this.ctx.currentTime + fade + 0.05);
    } catch (_) {}
  }

  /* ---- generative background music -----------------------------------
     A long-form synth bed for browsing the board. It is built from
     SECTIONS of 8-16 bars; each section rolls its own key drift, chord
     walk, texture (pad wave, filter, arp), rhythm masks and mood
     (sparse / calm / flow / pulse). Masks also re-roll every 2 bars, so
     within a section it keeps moving. Nothing ever repeats - the
     effective loop is many minutes long. Soft, minor-key, low in the
     mix. Click-safe envelopes + a lookahead scheduler that skips ahead
     silently if the main thread stalls (no catch-up crackle). */
  music(track = 'bbs') {
    this._musicWanted = track;
    if (!this.enabled) return;
    this._ensure();
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    if (this._musTimer) return;                 // already playing
    const bus = this.ctx.createGain();
    bus.gain.setValueAtTime(0.0001, this.ctx.currentTime);
    bus.gain.exponentialRampToValueAtTime(1, this.ctx.currentTime + 2.5);   // slow fade-in
    bus.connect(this.musicBus || this.master);
    this._musOut = bus;
    this._musPad = null;
    this._mus = {
      bpm: 72 + ((Math.random() * 16) | 0),
      baseRoot: 45 + [0, 0, -2, 3, -4, 5][(Math.random() * 6) | 0],
      step: 0,
      nextT: this.ctx.currentTime + 0.12,
      secEndBar: 0,
      sec: null,
    };
    this._musSection(0);
    this._musTimer = setInterval(() => this._musSchedule(), 25);
  }

  stopMusic(fade = 1.4) {
    if (this._musTimer) { clearInterval(this._musTimer); this._musTimer = null; }
    this._mus = null; this._musPad = null;
    const bus = this._musOut; this._musOut = null;
    if (!bus || !this.ctx) return;
    try {
      const t = this.ctx.currentTime;
      bus.gain.cancelScheduledValues(t);
      bus.gain.setValueAtTime(Math.max(0.0001, bus.gain.value), t);
      bus.gain.exponentialRampToValueAtTime(0.0001, t + fade);
    } catch (_) {}
  }

  _hzMidi(n) { return 440 * Math.pow(2, (n - 69) / 12); }

  _musSection(bar) {
    const m = this._mus; if (!m) return;
    const CH = {
      i: [0, 3, 7], ii: [2, 5, 8], III: [3, 7, 10], iv: [5, 8, 12],
      v: [7, 10, 14], VI: [8, 12, 15], VII: [10, 14, 17], i9: [0, 3, 7, 14],
    };
    const NEXT = {
      i: ['VI', 'iv', 'III', 'VII', 'v', 'ii', 'i9'], ii: ['v', 'VII', 'i'],
      III: ['VI', 'iv', 'VII'], iv: ['VII', 'v', 'i', 'ii'], v: ['VI', 'i', 'iv'],
      VI: ['iv', 'III', 'ii', 'VII'], VII: ['i', 'VI', 'v'], i9: ['iv', 'VI', 'v'],
    };
    const MOODS = ['sparse', 'calm', 'calm', 'flow', 'flow', 'flow', 'pulse'];
    const s = {
      mood: MOODS[(Math.random() * MOODS.length) | 0],
      keyShift: [0, 0, 0, -2, 3, -4, 5, -5, 7][(Math.random() * 9) | 0],
      lenBars: 8 + ((Math.random() * 9) | 0),
      progLen: 4 + ((Math.random() * 4) | 0),
      prog: [],
      padWave: ['sine', 'triangle', 'sawtooth'][(Math.random() * 3) | 0],
      arpEvery: [2, 2, 3, 4, 6][(Math.random() * 5) | 0],
      arpOct: 1 + ((Math.random() * 2) | 0),
      cutoff: 480 + ((Math.random() * 900) | 0),
      swing: Math.random() < 0.5 ? 0 : 0.012 + Math.random() * 0.02,
      bell: Math.random() < 0.45 ? 0.1 + Math.random() * 0.22 : 0,
    };
    let k = 'i';
    for (let b = 0; b < s.progLen; b++) {
      s.prog.push(CH[k]);
      const opts = NEXT[k] || ['i'];
      k = opts[(Math.random() * opts.length) | 0];
    }
    s.root = m.baseRoot + s.keyShift;
    s.drum = { sparse: 0, calm: 0.16, flow: 0.5, pulse: 0.92 }[s.mood];
    s.arpBase = s.mood === 'flow' || s.mood === 'pulse';
    m.sec = s;
    m.secEndBar = bar + s.lenBars;
    this._musRoll();
  }

  _musRoll() {
    const s = this._mus && this._mus.sec; if (!s) return;
    const mask = (d) => { let x = 0; for (let i = 0; i < 16; i++) if (Math.random() < d) x |= (1 << i); return x; };
    s.kickM = (0b0000000100000001) | mask(s.mood === 'pulse' ? 0.12 : 0.03);
    s.hatM  = s.mood === 'pulse' ? (0b1010101010101010 ^ mask(0.15)) : mask(s.mood === 'flow' ? 0.14 : 0.05);
    s.bassM = s.drum > 0.3 ? (0b0001000100010001 | mask(0.12)) : mask(0.05);
    s.arpOn = s.arpBase || Math.random() < 0.35;
  }

  _musSchedule() {
    const m = this._mus; if (!m || !this.ctx) return;
    const stepDur = 60 / m.bpm / 4;
    const now = this.ctx.currentTime;
    if (m.nextT < now - 0.03) {                 // fell behind: fast-forward silently
      const skip = Math.ceil((now - m.nextT) / stepDur);
      m.step += skip; m.nextT += skip * stepDur;
    }
    let guard = 0;
    while (m.nextT < now + 0.2 && guard++ < 48) {
      this._musStep(m.step, Math.max(m.nextT, now + 0.004));
      m.step++; m.nextT += stepDur;
    }
  }

  _musStep(i, t) {
    const m = this._mus, s = m.sec; if (!s) return;
    const bar = (i / 16) | 0, sib = i & 15;
    if (sib === 0) {
      if (i > 0 && bar >= m.secEndBar) this._musSection(bar);
      else if (i > 0 && bar % 2 === 0) this._musRoll();
    }
    const sec = m.sec;
    const chord = sec.prog[bar % sec.prog.length];
    const barLen = (60 / m.bpm) * 4;

    if (sib === 0) {
      this._mPad(t, chord.map((x) => sec.root + x), barLen * 1.04, sec);
      if (sec.bell && Math.random() < sec.bell) {
        const pent = [0, 3, 5, 7, 10, 12];
        const n = sec.root + 24 + pent[(Math.random() * pent.length) | 0] + 12 * ((Math.random() * 2) | 0);
        this._mBell(t + Math.random() * 0.12, n);
      }
    }
    const sw = (sib % 2) ? sec.swing : 0;

    if (sec.drum > 0 && (sec.kickM & (1 << sib))) this._mKick(t);
    if (sec.drum > 0.35 && (sib === 4 || sib === 12)) this._mBrush(t);
    if (sec.hatM & (1 << sib)) this._mHat(t + sw);

    if (sec.bassM & (1 << sib)) {
      const n = sec.root - 12 + chord[0] + (Math.random() < 0.1 ? 7 : 0);
      this._mVoice(this._hzMidi(n), t, sec.drum > 0.3 ? 0.2 : 0.3, { type: 'sawtooth', g: 0.09, cutoff: 380, glide: this._hzMidi(n) * 0.985 });
    }

    if (sec.arpOn && sib % sec.arpEvery === 0) {
      const seq = chord.concat(chord.map((x) => x + 12));
      const idx = ((i / sec.arpEvery) | 0) % seq.length;
      const n = sec.root + 12 * sec.arpOct + seq[idx] + (sib === 0 ? 12 : 0);
      this._mVoice(this._hzMidi(n), t + sw, 0.19, { type: 'triangle', g: 0.033, cutoff: sec.cutoff });
      this._mVoice(this._hzMidi(n) * 2.001, t + sw, 0.10, { type: 'sine', g: 0.012, cutoff: sec.cutoff * 2 });
    }
  }

  _mPad(t, notes, dur, s) {
    if (this._musPad) { try { this._musPad.o.forEach((o) => o.stop(t + 0.35)); } catch (_) {} }
    const g = this.ctx.createGain();
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(0.028, t + Math.min(1.2, dur * 0.35));
    g.gain.setValueAtTime(0.028, t + dur * 0.55);
    g.gain.linearRampToValueAtTime(0.0001, t + dur);
    const lp = this.ctx.createBiquadFilter();
    lp.type = 'lowpass'; lp.frequency.value = s ? s.cutoff : 700; lp.Q.value = 0.4;
    g.connect(lp).connect(this._musOut || this.master);
    const o = notes.map((mm, k) => {
      const osc = this.ctx.createOscillator();
      osc.type = k === 0 ? (s ? s.padWave : 'sawtooth') : 'triangle';
      osc.frequency.value = this._hzMidi(mm);
      osc.detune.value = (Math.random() - 0.5) * 9;
      osc.connect(g); osc.start(t); osc.stop(t + dur + 0.1); return osc;
    });
    this._musPad = { g, o };
  }

  _mVoice(hz, t, dur, { type = 'triangle', g = 0.04, cutoff = 1200, glide = null } = {}) {
    const o = this.ctx.createOscillator();
    const ga = this.ctx.createGain();
    const lp = this.ctx.createBiquadFilter();
    lp.type = 'lowpass'; lp.frequency.value = cutoff;
    o.type = type; o.frequency.setValueAtTime(hz, t);
    if (glide) o.frequency.exponentialRampToValueAtTime(glide, t + dur);
    ga.gain.setValueAtTime(0.0001, t);
    ga.gain.exponentialRampToValueAtTime(g, t + 0.01);
    ga.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(lp).connect(ga).connect(this._musOut || this.master);
    o.start(t); o.stop(t + dur + 0.03);
  }

  _mBell(t, midi) {
    const hz = this._hzMidi(midi);
    [1, 2.004, 3.01].forEach((mul, k) => {
      const o = this.ctx.createOscillator(), ga = this.ctx.createGain();
      o.type = 'sine'; o.frequency.value = hz * mul;
      const peak = [0.03, 0.014, 0.006][k];
      ga.gain.setValueAtTime(0.0001, t);
      ga.gain.exponentialRampToValueAtTime(peak, t + 0.005);
      ga.gain.exponentialRampToValueAtTime(0.0001, t + 1.6 + k * 0.4);
      o.connect(ga).connect(this._musOut || this.master);
      o.start(t); o.stop(t + 2.4);
    });
  }

  _mKick(t) {
    const o = this.ctx.createOscillator(), g = this.ctx.createGain();
    o.frequency.setValueAtTime(115, t);
    o.frequency.exponentialRampToValueAtTime(44, t + 0.11);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(0.32, t + 0.004);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.17);
    o.connect(g).connect(this._musOut || this.master);
    o.start(t); o.stop(t + 0.22);
  }

  _mBrush(t) {
    const n = Math.max(1, (this.ctx.sampleRate * 0.14) | 0);
    const b = this.ctx.createBuffer(1, n, this.ctx.sampleRate), ch = b.getChannelData(0);
    for (let k = 0; k < n; k++) ch[k] = (Math.random() * 2 - 1) * (1 - k / n);
    const s = this.ctx.createBufferSource(); s.buffer = b;
    const f = this.ctx.createBiquadFilter(); f.type = 'bandpass'; f.frequency.value = 2400; f.Q.value = 0.6;
    const g = this.ctx.createGain();
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(0.05, t + 0.006);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.14);
    s.connect(f).connect(g).connect(this._musOut || this.master);
    s.start(t); s.stop(t + 0.18);
  }

  _mHat(t) {
    const d = 0.03;
    const n = Math.max(1, (this.ctx.sampleRate * d) | 0);
    const b = this.ctx.createBuffer(1, n, this.ctx.sampleRate), ch = b.getChannelData(0);
    for (let k = 0; k < n; k++) ch[k] = Math.random() * 2 - 1;
    const s = this.ctx.createBufferSource(); s.buffer = b;
    const f = this.ctx.createBiquadFilter(); f.type = 'highpass'; f.frequency.value = 8000;
    const g = this.ctx.createGain();
    g.gain.setValueAtTime(0.045, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + d);
    s.connect(f).connect(g).connect(this._musOut || this.master);
    s.start(t); s.stop(t + d + 0.02);
  }

  /* ---- the modem ---------------------------------------------------
     Returns a Promise that resolves when "CONNECT" would print. Fires
     opts.onEvent(name) at each stage so the boot text can sync. */
  async dial(digits, opts = {}) {
    this._ensure();
    if (!this.ctx) { return this._silentDial(opts); }
    if (this.ctx.state === 'suspended') await this.ctx.resume();
    const emit = (n) => opts.onEvent && opts.onEvent(n);
    const g = this.master.gain;
    const wasMuted = !this.enabled;
    if (wasMuted) g.setValueAtTime(0.0001, this.ctx.currentTime); // still animate, silently

    let t = this.ctx.currentTime + 0.05;

    // 1. pick up the line: relay click
    this._noise(t, 0.04, { gain: 0.2, band: 600 });
    t += 0.15;
    emit('offhook');

    // 2. dial tone (350 + 440 Hz)
    this._tone(350, t, 0.7, { gain: 0.09 });
    this._tone(440, t, 0.7, { gain: 0.09 });
    await this._wait(0.75);
    t = this.ctx.currentTime;
    emit('dialtone');

    // 3. DTMF the number
    const seq = (digits || '5551234').split('');
    for (const ch of seq) {
      const pair = DTMF[ch];
      if (pair) {
        this._tone(pair[0], t, 0.09, { gain: 0.11 });
        this._tone(pair[1], t, 0.09, { gain: 0.11 });
      }
      t += 0.13;
    }
    await this._wait(seq.length * 0.13 + 0.1);
    emit('dialed');

    // 4. ring-ring x2 (440 + 480 Hz, 2s on / 1s off cadence, compressed)
    t = this.ctx.currentTime;
    for (let i = 0; i < 2; i++) {
      this._tone(440, t, 0.8, { gain: 0.08 });
      this._tone(480, t, 0.8, { gain: 0.08 });
      t += 1.1;
    }
    await this._wait(2.0);
    emit('ring');

    // 5. remote pickup + handshake: carrier tones, answer tone, scrambled data
    t = this.ctx.currentTime;
    this._tone(2100, t, 0.4, { gain: 0.10 });                    // answer tone
    t += 0.45;
    this._tone(1200, t, 0.25, { type: 'sine', gain: 0.08 });     // originate
    this._tone(2250, t, 0.25, { type: 'sine', gain: 0.06 });
    t += 0.3;
    // training: swept tones + noise bursts
    for (let i = 0; i < 6; i++) {
      const f = 900 + Math.random() * 1600;
      this._tone(f, t, 0.12, { type: 'sine', gain: 0.06, glide: f * (0.6 + Math.random() * 0.8) });
      this._noise(t + 0.02, 0.09, { gain: 0.08, band: 1000 + Math.random() * 2000, q: 0.5 });
      t += 0.14;
    }
    emit('handshake');
    // the classic "kshhhhh" scrambled data settle
    this._noise(t, 0.9, { gain: 0.11, band: 1800, q: 0.4 });
    this._tone(1800, t, 0.9, { type: 'sawtooth', gain: 0.03, glide: 1650 });
    this._tone(600, t, 0.9, { type: 'sine', gain: 0.03 });
    await this._wait(1.0);

    // 6. carrier lock -> quiet hiss, then CONNECT
    t = this.ctx.currentTime;
    this._noise(t, 0.6, { gain: 0.03, band: 1000, q: 0.3 });
    await this._wait(0.4);
    emit('connect');
    if (this.enabled) this.connectChime();
    if (wasMuted) g.setValueAtTime(0.5, this.ctx.currentTime);
  }

  _silentDial(opts) {
    const emit = (n) => opts.onEvent && opts.onEvent(n);
    const steps = ['offhook', 'dialtone', 'dialed', 'ring', 'handshake', 'connect'];
    return steps.reduce(
      (p, s, i) => p.then(() => new Promise(r => setTimeout(() => { emit(s); r(); }, i === 0 ? 200 : 700))),
      Promise.resolve()
    );
  }

  _wait(s) { return new Promise(r => setTimeout(r, s * 1000)); }
}

export const sound = new Sound();
