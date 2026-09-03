/* boot.js - power-on, BIOS POST, AT modem dial-in, then "telnet bbs.thugs.red".
   Resolves with the { connection, frame } payload from /api/session. */

import { sound } from './audio.js?v=4';
import { connect } from './net.js?v=4';

let FAST = false;
export function skipBoot() { FAST = true; }
const sleep = ms => new Promise(r => setTimeout(r, FAST ? Math.min(ms, 4) : ms));

export async function runBoot(term, { skip = false } = {}) {
  if (skip) FAST = true;
  const crt = document.getElementById('crt');
  crt.classList.add('on');

  // fetch the session in parallel with the animation
  const sessionP = connect();

  if (skip) {
    return sessionP;
  }

  if (FAST) {
    crt.classList.add('on');
    return sessionP;
  }

  crt.classList.add('powering-on');
  const scr = [];
  const push = (s = '') => { scr.push(s); if (scr.length > term.rows) scr.shift(); term.paint(scr); };
  const type = async (s, cps = 900) => {
    let cur = scr.length ? scr[scr.length - 1] : (scr.push(''), scr[0]);
    for (const ch of s) {
      cur += ch;
      scr[scr.length - 1] = cur;
      term.paint(scr);
      sound.key();
      await sleep(1000 / cps + Math.random() * 18);
    }
  };

  await sleep(650); // CRT collapse/expand

  // ---- BIOS POST ----
  push('|08THUGS(red) Systems  BIOS v1.0.92   (C) 1991-2026');
  push('|08============================================================');
  await sleep(220);
  push('|07Main Processor    : 80486DX2  66 MHz');
  await sleep(90);
  push('|07Math Coprocessor  : Present');
  await sleep(90);
  push('|07Memory Test       : |1516384K OK');
  await sleep(260);
  push('|07Detecting drives  ... |10done');
  await sleep(140);
  push('|07Serial Ports      : |153F8 |072F8    |07Parallel : |15378');
  await sleep(120);
  push('|07Modem             : |10USRobotics Courier V.Everything  on COM2');
  await sleep(400);
  push('');
  push('|07Loading TERM.EXE ...');
  await sleep(500);
  push('');
  push('|10-CONNECTED TERMINAL v3.11-   |08type ATZ to init modem');
  push('');

  // ---- AT command dance ----
  push('|15ATZ');
  await sleep(280);
  push('|07OK');
  await sleep(220);
  push('|15ATDT ');
  const conn = await sessionP.catch(() => null);
  const digits = (conn && conn.connection && conn.connection.dial_digits) || '5551234';
  const phone = (conn && conn.connection && conn.connection.phone) || '(555) 123-4567';
  const host = (conn && conn.connection && conn.connection.telnet_host) || 'bbs.thugs.red';
  const baud = (conn && conn.connection && conn.connection.baud) || 57600;

  await type(digits.replace(/(\d{3})(\d{3})(\d{4})/, 'T$1-$2-$3').replace(/^T/, ''), 7);
  push('');
  push('|08dialing ' + phone + ' ...');

  const maint = !!(conn && conn.connection && conn.connection.maintenance);

  // ---- maintenance: engaged tone, no carrier ----
  if (maint) {
    push('|08[ dial tone ]');
    await sleep(FAST ? 5 : 500);
    push('|08[ tones sent ]');
    await sleep(FAST ? 5 : 500);
    push('|08[ ringing ... ]');
    await sleep(FAST ? 5 : 700);
    push('');
    push('|11[ BUSY - the line is engaged ]');
    push('|12NO CARRIER');
    sound.startBusy();
    return sessionP;
  }

  // ---- the modem itself ----
  const stageText = {
    dialtone: '|08[ dial tone ]',
    dialed:   '|08[ tones sent ]',
    ring:     '|08[ ringing ... ]',
    handshake:'|08[ carrier detected - negotiating ]',
    connect:  '|10CONNECT ' + baud + '/ARQ/V90/LAPM',
  };
  if (FAST) {
    Object.values(stageText).forEach(push);
  } else {
    await sound.dial(digits, {
      onEvent: (name) => { if (stageText[name]) push(stageText[name]); },
    });
  }

  await sleep(300);
  push('');
  push('|10Escape character is ^].');
  await sleep(200);
  push('|15$ telnet ' + host);
  scr[scr.length - 1] = '|15$ ';
  await type('telnet ' + host, 14);
  await sleep(280);
  push('|08Trying ' + phone.replace(/\D/g, '') + ' ...');
  await sleep(180);
  push('|08Connected to ' + host + '.');
  await sleep(220);

  return sessionP;
}
