/* chat.js - node chat client. Activated when a frame arrives with mode "chat".
   Long-polls /api/chat/poll and posts to /api/chat/say. */

import { chat } from './net.js?v=6';
import { sound } from './audio.js?v=6';

export function installChat(term) {
  const st = {
    active: false,
    since: 0,
    channel: 'main',
    handle: 'you',
    header: [],
    msgs: [],
    present: [],
    input: '',
    timer: null,
    pollMs: 1800,
  };

  term.onChatEnter = (frame) => {
    st.active = true;
    st.header = (frame.lines || []).slice();
    st.since = (frame.meta && frame.meta.since) || 0;
    st.channel = (frame.meta && frame.meta.channel) || 'main';
    st.handle = (frame.meta && frame.meta.handle) || 'you';
    st.pollMs = (frame.meta && frame.meta.poll_ms) || 1800;
    st.msgs = [];
    st.input = '';
    render();
    poll();
    st.timer = setInterval(poll, st.pollMs);
  };

  term.onChatKey = (k, ev) => {
    if (!st.active) return;
    if (k === 'ESC') { leave(); return; }
    if (k === 'ENTER') {
      const v = st.input.trim();
      st.input = '';
      render();
      if (v) chat.say(v).then(() => poll());
      return;
    }
    if (k === 'BACKSPACE') { st.input = st.input.slice(0, -1); sound.key(); return render(); }
    if (k === 'SPACE') { st.input += ' '; sound.key(); return render(); }
    if (k && k.length === 1 && !ev.ctrlKey && !ev.metaKey) { st.input += k; sound.key(); return render(); }
  };

  function leave() {
    st.active = false;
    if (st.timer) clearInterval(st.timer);
    st.timer = null;
    term._send({ cmd: 'leave' });
  }

  function poll() {
    if (!st.active) return;
    chat.poll(st.since).then(d => {
      if (!st.active || !d) return;
      if (Array.isArray(d.messages) && d.messages.length) {
        st.msgs.push(...d.messages);
        st.msgs = st.msgs.slice(-400);
        st.since = d.last || st.since;
        if (d.messages.some(m => m.handle !== st.handle && m.kind !== 'system')) sound.move();
      }
      st.present = d.present || st.present;
      render();
    }).catch(() => {});
  }

  function render() {
    const rows = st.header.slice();
    const bodyRows = term.rows - rows.length - 3;
    const shown = st.msgs.slice(-Math.max(4, bodyRows));
    for (const m of shown) {
      const t = (m.created_at || '').slice(11, 16);
      if (m.kind === 'system') {
        rows.push([{ s: '  -- ' + m.body + ' --', f: 8, b: 0, o: false, k: false }]);
      } else if (m.kind === 'me') {
        rows.push([{ s: '  * ' + m.handle + ' ' + m.body, f: 13, b: 0, o: false, k: false }]);
      } else {
        rows.push([
          { s: '  ' + t + ' ', f: 8, b: 0, o: false, k: false },
          { s: '<' + m.handle + '> ', f: m.handle === st.handle ? 11 : 14, b: 0, o: true, k: false },
          { s: m.body, f: 7, b: 0, o: false, k: false },
        ]);
      }
    }
    while (rows.length < term.rows - 2) rows.push([]);
    rows.push([{ s: '  online: ', f: 8, b: 0, o: false, k: false },
               { s: (st.present.join(', ') || '(just you)'), f: 10, b: 0, o: false, k: false }]);
    rows.push([
      { s: '  say> ', f: 10, b: 0, o: true, k: false },
      { s: st.input, f: 15, b: 0, o: false, k: false },
      { s: ' ', f: 0, b: 15, o: false, k: true },
      { s: '    (ESC leaves chat)', f: 8, b: 0, o: false, k: false },
    ]);
    term.paint(rows);
  }
}
