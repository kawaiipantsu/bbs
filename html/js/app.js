/* app.js - wires the CRT, terminal, audio, boot sequence and HUD together. */

import { Terminal } from './terminal.js';
import { runBoot, skipBoot } from './boot.js';
import { sound } from './audio.js';
import { action, ticker, state } from './net.js';
import { installChat } from './chat.js';
import { installControls } from './controls.js';

const $ = sel => document.querySelector(sel);
const LS = {
  get: (k, d) => { try { return localStorage.getItem(k) ?? d; } catch { return d; } },
  set: (k, v) => { try { localStorage.setItem(k, v); } catch {} },
};

window.addEventListener('error', (e) => {
  const n = document.querySelector('#hud-node');
  if (n) n.textContent = 'JS ERROR: ' + (e.message || e.error) + ' @ ' + (e.filename || '').split('/').pop() + ':' + e.lineno;
});
window.addEventListener('unhandledrejection', (e) => {
  const n = document.querySelector('#hud-node');
  if (n) n.textContent = 'PROMISE REJECT: ' + (e.reason && (e.reason.message || e.reason));
});

const screenEl = $('#screen');
const crt = $('#crt');
let term;
let controls;
let started = false;
let maintenance = false;
let awaitingGoto = document.documentElement.dataset.goto || '';

/* ---- gesture gate: dial in on first key/click --------------------- */
function showGate() {
  const rows = [];
  const pad = Math.floor(term.rows / 2) - 3;
  for (let i = 0; i < pad; i++) rows.push([]);
  const line = (s, f = 7) => rows.push([{ s, f, b: 0, o: f >= 8, k: false }]);
  const centre = (s, f) => {
    const p = Math.max(0, Math.floor((term.cols - s.length) / 2));
    line(' '.repeat(p) + s, f);
  };
  centre('THUGS(red) BBS', 9);
  rows.push([]);
  centre('- a bulletin board for people who miss the sound -', 8);
  rows.push([]); rows.push([]);
  centre('[ PRESS ANY KEY OR CLICK TO DIAL IN ]', 15);
  rows.push([]);
  centre('sound is ON for the dial-up  ·  mute it any time with the SOUND button', 8);
  term.paint(rows);
}

async function start(skip) {
  if (started) return;
  started = true;

  // audio: on for the modem unless the user has muted before
  const savedSound = LS.get('bbs_sound', 'on');
  sound.setEnabled(savedSound !== 'off');
  syncSoundBtn();

  try {
    const payload = await runBoot(term, { skip });
    applyConnection(payload && payload.connection);
    if (payload && payload.connection && payload.connection.maintenance) {
      maintenance = true;
      sound.startBusy();
      term.renderFrame(payload.frame || {
        mode: 'pager',
        lines: [[{ s: '  BUSY - the board is down for maintenance.', f: 12, b: 0, o: true }]],
      });
      LS.set('bbs_booted', '1');
      return;
    }
    if (payload && payload.frame) {
      term.renderFrame(payload.frame);
      maybeGoto();
    } else {
      term.renderFrame({ mode: 'pager', lines: [[{ s: '  NO CARRIER - could not reach the board. Reload to redial.', f: 9, b: 0, o: true }]] });
    }
  } catch (e) {
    term.renderFrame({ mode: 'pager', lines: [[{ s: '  NO CARRIER - ' + (e && e.message || 'connection failed'), f: 9, b: 0 }]] });
  }
  LS.set('bbs_booted', '1');
}

function applyConnection(conn) {
  if (!conn) return;
  state.connection = conn;
  if (conn.cols) term.cols = conn.cols;
  if (conn.rows) term.rows = conn.rows;
  const crtCfg = conn.crt || {};
  document.documentElement.style.setProperty('--crt-intensity', String(crtCfg.intensity ?? 0.85));
  const crtOn = LS.get('bbs_crt', crtCfg.scanlines === false ? 'off' : 'on') !== 'off';
  crt.classList.toggle('no-crt', !crtOn);
  $('#btn-crt').setAttribute('aria-pressed', String(crtOn));
  $('#btn-crt').textContent = 'CRT: ' + (crtOn ? 'ON' : 'OFF');
  $('#hud-node').textContent = 'NODE ' + conn.node + ' / ' + conn.nodes_total + '  ·  ' + conn.phone;
  term.resize();
}

function send(payload) {
  return action(payload).then(frame => {
    if (!frame) return;
    if (frame.error) {
      term.renderFrame({ mode: (term.frame && term.frame.mode) || 'menu',
        lines: (term.frame && term.frame.lines || []).concat([[{ s: '  ! ' + frame.error, f: 9, b: 0, o: true }]]),
        meta: term.frame && term.frame.meta || {} });
      sound.error();
      return;
    }
    if (frame.whoami) state.whoami = frame.whoami;
    term.renderFrame(frame);
    if (frame.meta && frame.meta.hangup) return powerOff();
    maybeGoto();
  }).catch(err => {
    term.renderFrame({ mode: 'pager', lines: [[{ s: '  CARRIER LOST - ' + (err && err.message || 'network error'), f: 9, b: 0 }]] });
  });
}

function maybeGoto() {
  if (!awaitingGoto) return;
  if (term.frame && term.frame.view === 'menu') {
    const g = awaitingGoto; awaitingGoto = '';
    send({ cmd: 'goto', goto: g });
  }
}

function powerOff() {
  crt.classList.remove('powering-on');
  crt.classList.add('powering-off');
  setTimeout(() => {
    crt.classList.remove('on', 'powering-off');
    term.paint([[], [], [{ s: '   NO CARRIER', f: 9, b: 0, o: true }], [], [{ s: '   The line is free. Reload the page to call back.', f: 8, b: 0 }]]);
  }, 460);
}

/* ---- HUD --------------------------------------------------------- */
function syncSoundBtn() {
  const b = $('#btn-sound');
  b.textContent = 'SOUND: ' + (sound.enabled ? 'ON' : 'OFF');
  b.setAttribute('aria-pressed', String(sound.enabled));
}

function hud() {
  $('#btn-sound').addEventListener('click', () => {
    sound.setEnabled(!sound.enabled);
    LS.set('bbs_sound', sound.enabled ? 'on' : 'off');
    syncSoundBtn();
    if (sound.enabled) sound.beep();
  });
  $('#btn-crt').addEventListener('click', () => {
    const on = crt.classList.toggle('no-crt');
    const now = !on;
    LS.set('bbs_crt', now ? 'on' : 'off');
    $('#btn-crt').setAttribute('aria-pressed', String(now));
    $('#btn-crt').textContent = 'CRT: ' + (now ? 'ON' : 'OFF');
    crt.classList.add('degauss');
    setTimeout(() => crt.classList.remove('degauss'), 700);
  });
  $('#btn-full').addEventListener('click', () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
  });
}

/* ---- ticker ---------------------------------------------------- */
function startTicker() {
  const track = $('#ticker-track');
  const load = () => ticker().then(d => {
    if (!d || !Array.isArray(d.lines) || !d.lines.length) return;
    const sep = '<span class="sep">◆</span>';
    let unit = d.lines.map(l =>
      /^NEWS:/.test(l) ? '<b>' + escapeHtml(l) + '</b>' : escapeHtml(l)
    ).join(sep) + sep;
    // pad short boards so one copy always overflows the viewport, then double it
    while (unit.replace(/<[^>]+>/g, '').length < 240) unit += unit;
    track.innerHTML = unit + unit;
    const secs = Math.max(20, Number(d.speed) || 60);
    track.style.animationDuration = secs + 's';
  }).catch(() => {});
  load();
  setInterval(load, 120000);
}
const escapeHtml = s => s.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

/* ---- keyboard ------------------------------------------------- */
function keyboard() {
  window.addEventListener('keydown', (ev) => {
    // let browser shortcuts through
    if (ev.metaKey || (ev.ctrlKey && !['s', 'l'].includes(ev.key.toLowerCase()))) return;
    if (ev.key === 'F11' || ev.key === 'F5' || ev.key === 'F12') return;

    if (ev.ctrlKey && ev.key.toLowerCase() === 's') {
      ev.preventDefault();
      $('#btn-sound').click();
      return;
    }
    if (ev.ctrlKey && ev.key.toLowerCase() === 'l') { ev.preventDefault(); term.render(); return; }

    // maintenance: the line is engaged - swallow input (F5 still reloads to retry)
    if (maintenance) { ev.preventDefault(); return; }

    if (controls && !controls.isPowered()) { ev.preventDefault(); controls.powerOn(); return; }

    if (!started) {
      ev.preventDefault();
      start(false);
      return;
    }
    if (!term.frame) { skipBoot(); return; }

    ev.preventDefault();
    sound.key();
    term.key(ev);
  }, { passive: false });

  window.addEventListener('click', () => {
    if (!started) start(false);
    else screenEl.focus();
  });
}

/* ---- go ------------------------------------------------------- */
function main() {
  const conn0 = { cols: 132, rows: 50 };
  term = new Terminal(screenEl, conn0);
  term.setSend(send);
  installChat(term);
  controls = installControls(term, {
    onPowerOff: () => { term.busy = true; },
    onPowerOn: () => { term.busy = false; },
  });
  hud();
  keyboard();
  startTicker();
  term.resize();
  showGate();

  // If we've booted before and the user prefers it, auto-skip the dial-up.
  const fast = new URLSearchParams(location.search).has('fast');
  if (fast) { start(true); }

  screenEl.focus();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', main);
} else {
  main();
}
