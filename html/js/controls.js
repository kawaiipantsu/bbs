/* controls.js - the physical monitor controls cut from the photo:
   two turnable knobs (brightness / contrast) and a power button that
   actually powers the CRT on and off with sound + effect. */

import { sound } from './audio.js?v=2';

const LS = {
  get: (k, d) => { try { const v = localStorage.getItem(k); return v == null ? d : v; } catch { return d; } },
  set: (k, v) => { try { localStorage.setItem(k, v); } catch {} },
};

const clamp01 = v => Math.max(0, Math.min(1, v));
const ANGLE_RANGE = 264;         // degrees of knob travel
const ANGLE_MIN = -132;

export function installControls(term, hooks = {}) {
  const crt = document.getElementById('crt');
  const root = document.documentElement;

  const val = {
    bright: parseFloat(LS.get('bbs_bright', '0.62')),
    contrast: parseFloat(LS.get('bbs_contrast', '0.55')),   // ~1.05 saturation
  };
  let powered = true;

  const applyBright = () => {
    const el = document.getElementById('knob-bright');
    el.style.setProperty('--a', (ANGLE_MIN + val.bright * ANGLE_RANGE).toFixed(1) + 'deg');
    root.style.setProperty('--user-brightness', (0.45 + val.bright * 1.05).toFixed(3));
    LS.set('bbs_bright', val.bright.toFixed(3));
  };
  const applyContrast = () => {
    // "CONTRAST" knob = colour intensity: 0 = monochrome CRT, 1 = punchy.
    const el = document.getElementById('knob-contrast');
    el.style.setProperty('--a', (ANGLE_MIN + val.contrast * ANGLE_RANGE).toFixed(1) + 'deg');
    root.style.setProperty('--user-saturate', (val.contrast * 1.9).toFixed(3));
    LS.set('bbs_contrast', val.contrast.toFixed(3));
  };
  applyBright();
  applyContrast();

  wireKnob(document.getElementById('knob-bright'), () => val.bright, v => { val.bright = clamp01(v); applyBright(); });
  wireKnob(document.getElementById('knob-contrast'), () => val.contrast, v => { val.contrast = clamp01(v); applyContrast(); });

  // ---- power button --------------------------------------------------
  const btn = document.getElementById('power-btn');
  btn.addEventListener('click', () => {
    if (powered) powerOff(); else powerOn();
  });

  function powerOff() {
    powered = false;
    sound.powerOff();
    crt.classList.remove('on');
    crt.classList.add('powering-off');
    setTimeout(() => {
      crt.classList.remove('powering-off');
      crt.classList.add('off');
    }, 430);
    hooks.onPowerOff && hooks.onPowerOff();
  }

  function powerOn() {
    powered = true;
    sound.unlock();
    sound.powerOn();
    crt.classList.remove('off');
    crt.classList.add('on', 'powering-on');
    setTimeout(() => crt.classList.remove('powering-on'), 1150);
    // redraw whatever was on screen
    if (term && term.frame) term.render();
    hooks.onPowerOn && hooks.onPowerOn();
  }

  return {
    isPowered: () => powered,
    powerOn, powerOff,
  };
}

function wireKnob(el, getVal, setVal) {
  if (!el) return;
  let dragging = false;
  let lastTick = 0;

  const step = (v) => {
    setVal(v);
    if (Math.abs(v - lastTick) > 0.045) { sound.tick(); lastTick = v; }
  };

  el.addEventListener('pointerdown', (e) => {
    dragging = true;
    el.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  el.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    // vertical drag: up = increase
    step(getVal() - e.movementY * 0.006 + e.movementX * 0.002);
  });
  const end = (e) => {
    dragging = false;
    try { el.releasePointerCapture(e.pointerId); } catch {}
  };
  el.addEventListener('pointerup', end);
  el.addEventListener('pointercancel', end);

  el.addEventListener('wheel', (e) => {
    e.preventDefault();
    step(getVal() - Math.sign(e.deltaY) * 0.04);
  }, { passive: false });

  el.addEventListener('keydown', (e) => {
    if (['ArrowUp', 'ArrowRight'].includes(e.key)) { e.preventDefault(); step(getVal() + 0.04); }
    if (['ArrowDown', 'ArrowLeft'].includes(e.key)) { e.preventDefault(); step(getVal() - 0.04); }
  });
}
