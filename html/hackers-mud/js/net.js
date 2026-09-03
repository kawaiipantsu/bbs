/* net.js - talks to /api/mud/* . Keeps the CSRF token fresh. */

export const S = { csrf: '', handle: '' };

async function call(path, opts = {}) {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 15000);
  let res;
  try {
    res = await fetch(path, {
      credentials: 'same-origin',
      signal: ctl.signal,
      method: opts.body ? 'POST' : 'GET',
      headers: {
        'Accept': 'application/json',
        ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
        ...(S.csrf ? { 'X-CSRF': S.csrf } : {}),
      },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
    });
  } catch (e) {
    clearTimeout(timer);
    return { ok: false, error: e.name === 'AbortError' ? 'timed out' : 'network error' };
  }
  clearTimeout(timer);
  let data = {};
  try { data = await res.json(); } catch { data = { ok: false, error: 'bad response' }; }
  if (data && data.csrf) S.csrf = data.csrf;
  if (data && data.handle) S.handle = data.handle;
  if (res.status === 419) data.stale = true;
  return data;
}

export const api = {
  whoami: () => call('/api/mud/whoami'),
  login: (handle, password) => call('/api/mud/login', { body: { handle, password } }),
  archetype: (choice) => call('/api/mud/archetype', { body: { choice } }),
  state: () => call('/api/mud/state'),
  cmd: (cmd) => call('/api/mud/cmd', { body: { cmd } }),
  logout: () => call('/api/mud/logout', { body: {} }),
  players: () => call('/api/mud/players'),
  inbox: () => call('/api/mud/inbox'),
  sms: (to, body) => call('/api/mud/sms', { body: { to, body } }),
  itemdex: () => call('/api/mud/itemdex'),
};
