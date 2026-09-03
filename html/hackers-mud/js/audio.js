/* audio.js - synthesised SFX + ambient beds for the graphical client.
   No sample files. Handles iOS unlock (silent-switch + suspended context). */

const MIX_DEFAULT = { master: 0.6, music: 0.32, sfx: 1.0, amb: 0.7, musicOn: true };

class Audio {
  constructor() {
    this.ctx = null; this.master = null; this.on = true; this._amb = null; this._ambKey = null;
    this.mix = { ...MIX_DEFAULT };
    try {
      const s = JSON.parse(localStorage.getItem('hm_mix') || '{}');
      this.mix = { ...MIX_DEFAULT, ...s };
    } catch (_) {}
    this._musTrack = null;
  }

  _ensure() {
    if (this.ctx) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    this.ctx = new AC();
    this.master = this.ctx.createGain();
    this.master.gain.value = this.mix.master;
    this.master.connect(this.ctx.destination);
    // per-bus gains so music can sit low under the effects
    this.sfxG = this.ctx.createGain(); this.sfxG.gain.value = this.mix.sfx; this.sfxG.connect(this.master);
    this.ambG = this.ctx.createGain(); this.ambG.gain.value = this.mix.amb; this.ambG.connect(this.master);
    this.musG = this.ctx.createGain(); this.musG.gain.value = this.mix.musicOn ? this.mix.music : 0; this.musG.connect(this.master);
    this.ctx.onstatechange = () => { if (this._unlocked && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {}); };
  }

  getMix() { return { ...this.mix }; }
  setMix(part) {
    this.mix = { ...this.mix, ...part };
    try { localStorage.setItem('hm_mix', JSON.stringify(this.mix)); } catch (_) {}
    if (!this.ctx) return;
    const t = this.ctx.currentTime, r = (g, v) => g && g.gain.setTargetAtTime(v, t, 0.05);
    r(this.master, this.mix.master);
    r(this.sfxG, this.mix.sfx);
    r(this.ambG, this.mix.amb);
    r(this.musG, this.mix.musicOn ? this.mix.music : 0);
    if (this.mix.musicOn && this._musTrack && !this._musTimer) this.music(this._musTrack, true);
  }

  unlock() {
    this._ensure();
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    try { const s = this.ctx.createBufferSource(); s.buffer = this.ctx.createBuffer(1, 1, 22050); s.connect(this.ctx.destination); s.start(0); } catch (_) {}
    if (!this._mel) {
      try {
        const el = document.createElement('audio');
        el.setAttribute('playsinline', ''); el.loop = true; el.volume = 0.001;
        el.src = 'data:audio/wav;base64,UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAAAAAAAAAAAAAAAAAAAAAA=';
        const p = el.play(); if (p && p.catch) p.catch(() => {});
        this._mel = el;
      } catch (_) {}
    }
    this._unlocked = true;
  }
  resume() { if (this.ctx && this._unlocked && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {}); }
  setOn(v) { this.on = !!v; if (!v) { this.ambientStop(); this.stopMusic(); } else { this.unlock(); if (this._musTrack) this.music(this._musTrack, true); } }
  get ready() { return this.on && this.ctx && this._unlocked; }

  _t(f, t, d, { type = 'sine', g = 0.15, glide = 0, dest } = {}) {
    const o = this.ctx.createOscillator(), ga = this.ctx.createGain();
    o.type = type; o.frequency.setValueAtTime(f, t);
    if (glide) o.frequency.exponentialRampToValueAtTime(glide, t + d);
    ga.gain.setValueAtTime(0.0001, t);
    ga.gain.exponentialRampToValueAtTime(g, t + 0.008);
    ga.gain.exponentialRampToValueAtTime(0.0001, t + d);
    o.connect(ga).connect(dest || this.master); o.start(t); o.stop(t + d + 0.03);
  }
  _n(t, d, { g = 0.12, band = 0, q = 1, dest } = {}) {
    const n = Math.max(1, (this.ctx.sampleRate * d) | 0);
    const b = this.ctx.createBuffer(1, n, this.ctx.sampleRate), ch = b.getChannelData(0);
    for (let i = 0; i < n; i++) ch[i] = Math.random() * 2 - 1;
    const s = this.ctx.createBufferSource(); s.buffer = b;
    const ga = this.ctx.createGain();
    ga.gain.setValueAtTime(0.0001, t);
    ga.gain.exponentialRampToValueAtTime(g, t + 0.01);
    ga.gain.exponentialRampToValueAtTime(0.0001, t + d);
    let node = s;
    if (band) { const f = this.ctx.createBiquadFilter(); f.type = 'bandpass'; f.frequency.value = band; f.Q.value = q; node.connect(f); node = f; }
    node.connect(ga).connect(dest || this.master); s.start(t); s.stop(t + d + 0.03);
  }

  ui(name) {
    if (!this.ready) return;
    const t = this.ctx.currentTime, D = { dest: this.sfxG };
    if (name === 'click') { this._t(1400, t, 0.03, { type: 'square', g: 0.03, ...D }); }
    else if (name === 'hover') { this._t(900, t, 0.02, { type: 'sine', g: 0.015, ...D }); }
    else if (name === 'open') { [600, 900].forEach((f, i) => this._t(f, t + i * 0.04, 0.08, { type: 'triangle', g: 0.04, ...D })); }
    else if (name === 'deny') { this._t(200, t, 0.14, { type: 'square', g: 0.05, ...D }); }
    else if (name === 'ok') { [700, 1050, 1400].forEach((f, i) => this._t(f, t + i * 0.05, 0.1, { type: 'triangle', g: 0.045, ...D })); }
  }

  sfx(name) {
    if (!this.ready) return;
    const t = this.ctx.currentTime;
    const T = (f, tt, d, o = {}) => this._t(f, tt, d, { ...o, dest: this.sfxG });
    const N = (tt, d, o = {}) => this._n(tt, d, { ...o, dest: this.sfxG });
    switch (name) {
      case 'step': case 'step2':
        N(t, 0.05, { g: 0.05, band: name === 'step' ? 240 : 200, q: 1.4 });
        T(name === 'step' ? 96 : 74, t, 0.04, { type: 'sine', g: 0.04, glide: 45 }); break;
      case 'door': N(t, 0.05, { g: 0.15, band: 170, q: 0.6 }); T(140, t + .03, .12, { type: 'square', g: .05, glide: 70 }); T(1200, t, .04, { type: 'square', g: .025 }); break;
      case 'swing': case 'miss': N(t, .13, { g: .06, band: 1700, q: .4 }); T(500, t, .11, { type: 'sine', g: .025, glide: 1600 }); break;
      case 'blade': T(1800, t, .13, { type: 'sawtooth', g: .045, glide: 400 }); N(t, .09, { g: .045, band: 3200, q: .5 }); break;
      case 'hit': N(t, .08, { g: .12, band: 380, q: .8 }); T(120, t, .09, { type: 'square', g: .07, glide: 60 }); break;
      case 'crit': N(t, .16, { g: .19, band: 300, q: .7 }); T(88, t, .22, { type: 'square', g: .11, glide: 40 }); T(240, t + .02, .18, { type: 'sawtooth', g: .06, glide: 80 }); this.shake && this.shake(6); break;
      case 'gun': N(t, .05, { g: .22, band: 900, q: .3 }); N(t, .18, { g: .08, band: 220, q: .4 }); T(70, t, .14, { type: 'square', g: .1, glide: 40 }); this.shake && this.shake(4); break;
      case 'enemyhit': N(t, .08, { g: .09, band: 500, q: .9 }); T(200, t, .09, { type: 'sawtooth', g: .05, glide: 110 }); break;
      case 'hurt': T(300, t, .16, { type: 'sawtooth', g: .08, glide: 140 }); N(t, .1, { g: .05, band: 700 }); this.shake && this.shake(8); break;
      case 'kill': T(180, t, .28, { type: 'square', g: .09, glide: 50 }); N(t + .02, .18, { g: .08, band: 260, q: .5 }); break;
      case 'aggro': T(140, t, .3, { type: 'sawtooth', g: .07, glide: 220 }); T(90, t + .05, .3, { type: 'square', g: .05, glide: 130 }); break;
      case 'coin': case 'buy': case 'sell': T(1660, t, .06, { type: 'square', g: .06 }); T(2100, t + .05, .08, { type: 'square', g: .06 }); if (name !== 'sell') T(2640, t + .11, .1, { type: 'triangle', g: .05 }); break;
      case 'pickup': T(880, t, .04, { type: 'square', g: .05 }); T(1320, t + .04, .05, { type: 'square', g: .045 }); break;
      case 'drink': N(t, .22, { g: .05, band: 600, q: 1.5 }); T(400, t, .2, { type: 'sine', g: .03, glide: 520 }); break;
      case 'eat': N(t, .08, { g: .07, band: 900, q: 1 }); N(t + .12, .08, { g: .06, band: 700, q: 1 }); break;
      case 'equip': N(t, .05, { g: .08, band: 2200, q: .6 }); T(600, t, .06, { type: 'square', g: .04, glide: 300 }); break;
      case 'hack': for (let i = 0; i < 4; i++) T(1400 + Math.random() * 900, t + i * .05, .04, { type: 'square', g: .035 }); break;
      case 'hackok': [660, 880, 1320].forEach((f, i) => T(f, t + i * .05, .09, { type: 'triangle', g: .05 })); break;
      case 'hackfail': T(200, t, .14, { type: 'sawtooth', g: .07 }); T(150, t + .12, .16, { type: 'sawtooth', g: .06 }); break;
      case 'levelup': [523, 659, 784, 1046, 1318].forEach((f, i) => T(f, t + i * .07, .16, { type: 'triangle', g: .06 })); break;
      case 'quest': [784, 1046, 1568].forEach((f, i) => T(f, t + i * .08, .14, { type: 'triangle', g: .055 })); break;
      case 'death': T(400, t, .5, { type: 'sine', g: .09, glide: 120 }); T(402, t, .5, { type: 'sine', g: .06 }); N(t + .4, .5, { g: .04, band: 400, q: .3 }); this.shake && this.shake(12); break;
      case 'trace': for (let i = 0; i < 6; i++) T(1800, t + i * .14, .06, { type: 'square', g: .05 }); break;
    }
  }

  /* ambient bed: filtered-noise floor + a slow synthwave pad chord */
  ambient(key) {
    if (!this.on) { this._ambKey = key; return; }
    this._ensure();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    if (this._ambKey === key && this._amb) return;
    this.ambientStop(0.6);
    this._ambKey = key;
    if (!this.ctx) return;

    const out = this.ctx.createGain();
    out.gain.setValueAtTime(0.0001, this.ctx.currentTime);
    out.gain.exponentialRampToValueAtTime(0.8, this.ctx.currentTime + 1.2);
    out.connect(this.ambG || this.master);

    const cfg = {
      rain: { type: 'bandpass', f: 1600, q: .4, g: .03, chord: [110, 164.81, 220], pg: .012 },
      hum: { type: 'lowpass', f: 180, q: .7, g: .05, chord: [65.4, 98, 130.8], pg: .016 },
      drip: { type: 'lowpass', f: 500, q: .6, g: .02, chord: [82.4, 110, 146.8], pg: .01 },
      wind: { type: 'bandpass', f: 500, q: .3, g: .035, chord: [73.4, 110, 174.6], pg: .012 },
      static: { type: 'highpass', f: 2200, q: .4, g: .02, chord: [130.8, 196, 261.6], pg: .014 },
      room: { type: 'lowpass', f: 320, q: .6, g: .02, chord: [98, 146.8, 196], pg: .012 },
    }[key] || { type: 'lowpass', f: 400, q: .5, g: .02, chord: [98, 146.8, 196], pg: .01 };

    const n = this.ctx.createBufferSource();
    const len = (this.ctx.sampleRate * 2) | 0;
    const b = this.ctx.createBuffer(1, len, this.ctx.sampleRate), ch = b.getChannelData(0);
    for (let i = 0; i < len; i++) ch[i] = Math.random() * 2 - 1;
    n.buffer = b; n.loop = true;
    const f = this.ctx.createBiquadFilter(); f.type = cfg.type; f.frequency.value = cfg.f; f.Q.value = cfg.q;
    const g = this.ctx.createGain(); g.gain.value = cfg.g;
    n.connect(f).connect(g).connect(out); n.start();

    const pad = this.ctx.createGain(); pad.gain.value = cfg.pg;
    const lp = this.ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 900;
    pad.connect(lp).connect(out);
    const oscs = cfg.chord.map((hz, i) => {
      const o = this.ctx.createOscillator(); o.type = i === 0 ? 'sawtooth' : 'triangle';
      o.frequency.value = hz; o.detune.value = (Math.random() - .5) * 8;
      o.connect(pad); o.start(); return o;
    });
    // slow filter sweep for movement
    const lfo = this.ctx.createOscillator(); lfo.frequency.value = 0.05;
    const lfg = this.ctx.createGain(); lfg.gain.value = 300;
    lfo.connect(lfg).connect(lp.frequency); lfo.start();

    this._amb = { out, nodes: [n, ...oscs, lfo] };
  }
  ambientStop(fade = 0.4) {
    const a = this._amb; this._amb = null; this._ambKey = null;
    if (!a || !this.ctx) return;
    try {
      a.out.gain.cancelScheduledValues(this.ctx.currentTime);
      a.out.gain.setValueAtTime(Math.max(0.0001, a.out.gain.value), this.ctx.currentTime);
      a.out.gain.exponentialRampToValueAtTime(0.0001, this.ctx.currentTime + fade);
      a.nodes.forEach(nn => { try { nn.stop(this.ctx.currentTime + fade + .05); } catch (_) {} });
    } catch (_) {}
  }

  /* ---- generative background music -----------------------------------
     Soft tech/synth. A lookahead step sequencer over a bank of minor-key
     chord progressions + rhythm masks that re-randomise every 4-bar phrase,
     so it loops but never quite repeats. Two moods: idle and battle. */
  music(track, force) {
    this._musTrack = track;
    if (!track) return this.stopMusic();
    if (!this.on || !this.mix.musicOn) return;
    this._ensure();
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
    if (this._musTimer && this._mus && this._mus.track === track && !force) return;
    this.stopMusic();

    const battle = track === 'battle';
    this._mus = {
      track,
      bpm: battle ? 138 + ((Math.random() * 8) | 0) : 80 + ((Math.random() * 8) | 0),
      root: (battle ? 45 : 45) + [0, 0, -2, 3, -5][(Math.random() * 5) | 0],   // MIDI-ish
      step: 0,
      nextT: this.ctx.currentTime + 0.08,
      seat: null,
    };
    this._musPhrase();
    this._musTimer = setInterval(() => this._musSchedule(), 25);
  }
  stopMusic() {
    if (this._musTimer) { clearInterval(this._musTimer); this._musTimer = null; }
    if (this._musPad) { try { this._musPad.g.gain.setTargetAtTime(0.0001, this.ctx.currentTime, 0.4); this._musPad.o.forEach(o => o.stop(this.ctx.currentTime + 1.2)); } catch (_) {} this._musPad = null; }
    this._mus = null;
  }
  _musPhrase() {
    const m = this._mus; if (!m) return;
    // minor-scale chords as semitone-triads from the key root
    const CH = { i: [0, 3, 7], III: [3, 7, 10], iv: [5, 8, 12], v: [7, 10, 14], VI: [8, 12, 15], VII: [10, 14, 17], ii: [2, 5, 8] };
    const PROGS = [['i', 'VI', 'III', 'VII'], ['i', 'iv', 'VII', 'VI'], ['i', 'VII', 'VI', 'VII'], ['i', 'v', 'VI', 'iv'], ['i', 'III', 'VII', 'iv'], ['i', 'VI', 'iv', 'v']];
    m.prog = PROGS[(Math.random() * PROGS.length) | 0].map(k => CH[k]);
    const mask = (density) => { let x = 0; for (let i = 0; i < 16; i++) if (Math.random() < density) x |= (1 << i); return x; };
    const battle = m.track === 'battle';
    m.drumK = battle ? (0b0001000100010001 | mask(0.15)) : (0b0000000100000001 | mask(0.06));
    m.bassM = battle ? (0b1010101010101010 ^ mask(0.2)) : (0b1000001010000010 | mask(0.08));
    m.arpM  = battle ? (0b1111011011110110) : (0b1010001010100010 | mask(0.15));
    m.arpDir = Math.random() < 0.5 ? 1 : -1;
    m.hatEvery = battle ? 1 : 2;
    m.leadBar = battle && Math.random() < 0.6 ? (Math.random() * 4) | 0 : -1;
  }
  _musSchedule() {
    const m = this._mus; if (!m || !this.ctx) return;
    const stepDur = 60 / m.bpm / 4;
    while (m.nextT < this.ctx.currentTime + 0.16) {
      this._musStep(m.step, m.nextT);
      m.step++; m.nextT += stepDur;
    }
  }
  _hz(midi) { return 440 * Math.pow(2, (midi - 69) / 12); }
  _musStep(i, t) {
    const m = this._mus, D = { dest: this.musG };
    const pos = i % 64;                 // 4-bar phrase, 16 steps/bar
    if (pos === 0 && i > 0) this._musPhrase();
    const bar = (i >> 4) & 3, sib = i & 15;
    const chord = m.prog[bar];
    const battle = m.track === 'battle';

    // --- drums ---
    if (m.drumK & (1 << sib)) this._kick(t);
    if (sib === 4 || sib === 12) this._snare(t);
    else if (battle && sib === 14 && Math.random() < 0.4) this._snare(t, 0.5);
    if (sib % m.hatEvery === 0) this._hat(t, (sib % 8 === 6) ? 0.11 : 0.028);

    // --- bass ---
    if (m.bassM & (1 << sib)) {
      const note = m.root - 12 + chord[0] + (Math.random() < 0.12 ? 7 : 0);
      this._t(this._hz(note), t, battle ? 0.16 : 0.24, { type: 'sawtooth', g: 0.16, glide: this._hz(note) * 0.98, dest: this.musG });
    }
    // --- arp pluck ---
    if (m.arpM & (1 << sib)) {
      const idx = (m.arpDir > 0 ? i : -i) % chord.length;
      const note = m.root + 12 + chord[((idx % chord.length) + chord.length) % chord.length] + (sib % 8 === 0 ? 12 : 0);
      this._t(this._hz(note), t, 0.16, { type: 'triangle', g: 0.055, dest: this.musG });
      this._t(this._hz(note) * 2.001, t, 0.09, { type: 'sine', g: 0.02, dest: this.musG });
    }
    // --- pad at each chord change ---
    if (sib === 0) this._pad(t, chord.map(s => m.root + s), (60 / m.bpm) * 4 * 1.05);
    // --- lead motif (battle) ---
    if (battle && bar === m.leadBar && sib === 0) {
      const sc = [0, 2, 3, 5, 7, 8, 10];
      let n = m.root + 24;
      for (let k = 0; k < 3; k++) {
        n += sc[(Math.random() * sc.length) | 0] * (Math.random() < 0.5 ? 1 : -1);
        this._t(this._hz(n), t + k * (60 / m.bpm) / 2, 0.28, { type: 'square', g: 0.045, dest: this.musG });
      }
    }
  }
  _pad(t, notes, dur) {
    if (this._musPad) { try { this._musPad.g.gain.setTargetAtTime(0.0001, t, 0.5); this._musPad.o.forEach(o => o.stop(t + 1.5)); } catch (_) {} }
    const g = this.ctx.createGain(); g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(0.03, t + 0.6);
    g.gain.setTargetAtTime(0.0001, t + dur * 0.6, dur * 0.4);
    const lp = this.ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 700;
    g.connect(lp).connect(this.musG);
    const o = notes.map((mm, k) => {
      const osc = this.ctx.createOscillator();
      osc.type = k === 0 ? 'sawtooth' : 'triangle';
      osc.frequency.value = this._hz(mm); osc.detune.value = (Math.random() - 0.5) * 10;
      osc.connect(g); osc.start(t); osc.stop(t + dur + 1.5); return osc;
    });
    this._musPad = { g, o };
  }
  _kick(t) {
    const o = this.ctx.createOscillator(), g = this.ctx.createGain();
    o.frequency.setValueAtTime(140, t); o.frequency.exponentialRampToValueAtTime(42, t + 0.12);
    g.gain.setValueAtTime(0.5, t); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.16);
    o.connect(g).connect(this.musG); o.start(t); o.stop(t + 0.2);
  }
  _snare(t, v = 1) {
    this._n(t, 0.13, { g: 0.26 * v, band: 1800, q: 0.5, dest: this.musG });
    this._t(190, t, 0.1, { type: 'triangle', g: 0.12 * v, glide: 130, dest: this.musG });
  }
  _hat(t, d) {
    const n = Math.max(1, (this.ctx.sampleRate * d) | 0);
    const b = this.ctx.createBuffer(1, n, this.ctx.sampleRate), ch = b.getChannelData(0);
    for (let k = 0; k < n; k++) ch[k] = Math.random() * 2 - 1;
    const s = this.ctx.createBufferSource(); s.buffer = b;
    const f = this.ctx.createBiquadFilter(); f.type = 'highpass'; f.frequency.value = 7000;
    const g = this.ctx.createGain(); g.gain.setValueAtTime(0.09, t); g.gain.exponentialRampToValueAtTime(0.0001, t + d);
    s.connect(f).connect(g).connect(this.musG); s.start(t); s.stop(t + d + 0.02);
  }
}

export const audio = new Audio();
