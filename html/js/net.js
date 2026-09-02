/* net.js - talks to the BBS API. Keeps the CSRF token fresh from every reply. */

export const state = {
  csrf: '',
  connection: null,
  whoami: null,
};

async function jsonFetch(path, opts = {}) {
  const res = await fetch(path, {
    credentials: 'same-origin',
    ...opts,
    headers: {
      'Accept': 'application/json',
      ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
      ...(state.csrf ? { 'X-CSRF': state.csrf } : {}),
      ...(opts.headers || {}),
    },
  });
  let data = null;
  const text = await res.text();
  try { data = text ? JSON.parse(text) : null; } catch { data = { error: text || ('HTTP ' + res.status) }; }
  if (data && data.csrf) state.csrf = data.csrf;
  if (data && data.connection && data.connection.csrf) state.csrf = data.connection.csrf;
  if (data && data.whoami) state.whoami = data.whoami;
  if (!res.ok && data && !data.error) data.error = 'HTTP ' + res.status;
  return { ok: res.ok, status: res.status, data };
}

/** Begin the call. Returns { connection, frame }. */
export function connect() {
  return jsonFetch('/api/session', { method: 'POST', body: '{}' }).then(r => r.data);
}

/** Send one interaction. `payload` = { key?, input?, cmd?, data?, goto? }. */
export function action(payload) {
  return jsonFetch('/api/action', { method: 'POST', body: JSON.stringify(payload) }).then(r => r.data);
}

export function whoami() {
  return jsonFetch('/api/whoami').then(r => r.data);
}

export function ticker() {
  return jsonFetch('/api/ticker').then(r => r.data);
}

export const chat = {
  poll: (since) => jsonFetch('/api/chat/poll?since=' + (since | 0)).then(r => r.data),
  say: (body) => jsonFetch('/api/chat/say', { method: 'POST', body: JSON.stringify({ body }) }).then(r => r.data),
};
