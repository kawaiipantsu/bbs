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
  }

  /** Call from a user gesture to satisfy autoplay policy. */
  unlock() {
    this._ensure();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
    this._unlocked = true;
  }

  setEnabled(on) {
    this.enabled = !!on;
    if (on) this.unlock();
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
