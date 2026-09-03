/* audio.js - synthesised SFX + ambient beds for the graphical client.
   No sample files. Handles iOS unlock (silent-switch + suspended context). */

class Audio {
  constructor() { this.ctx = null; this.master = null; this.on = true; this._amb = null; this._ambKey = null; }

  _ensure() {
    if (this.ctx) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    this.ctx = new AC();
    this.master = this.ctx.createGain();
    this.master.gain.value = 0.5;
    this.master.connect(this.ctx.destination);
    this.ctx.onstatechange = () => { if (this._unlocked && this.ctx.state === 'suspended') this.ctx.resume().catch(() => {}); };
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
  setOn(v) { this.on = !!v; if (!v) this.ambientStop(); else this.unlock(); }
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
    const t = this.ctx.currentTime;
    if (name === 'click') { this._t(1400, t, 0.03, { type: 'square', g: 0.03 }); }
    else if (name === 'hover') { this._t(900, t, 0.02, { type: 'sine', g: 0.015 }); }
    else if (name === 'open') { [600, 900].forEach((f, i) => this._t(f, t + i * 0.04, 0.08, { type: 'triangle', g: 0.04 })); }
    else if (name === 'deny') { this._t(200, t, 0.14, { type: 'square', g: 0.05 }); }
    else if (name === 'ok') { [700, 1050, 1400].forEach((f, i) => this._t(f, t + i * 0.05, 0.1, { type: 'triangle', g: 0.045 })); }
  }

  sfx(name) {
    if (!this.ready) return;
    const t = this.ctx.currentTime, T = this._t.bind(this), N = this._n.bind(this);
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
    out.connect(this.master);

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
}

export const audio = new Audio();
