# HTTP API

Same-origin only. Every state-changing call needs the CSRF token from the last
response, sent as the `X-CSRF` header (or `csrf` in the JSON body). The cookie
is `bbs_node` (HttpOnly, SameSite=Lax).

## Terminal protocol

### `POST /api/session`
Begins the call. No body. Returns:

```jsonc
{
  "connection": {
    "phone": "(415) 203-1174", "dial_digits": "4152031174", "ip": "…",
    "node": 3, "nodes_total": 8, "nodes_free": 5, "baud": 57600,
    "telnet_host": "bbs.thugs.red", "site_name": "THUGS(red) BBS",
    "sound_default": false, "cols": 132, "rows": 50,
    "crt": { "intensity": 0.85, "scanlines": true, "flicker": true, "curvature": true },
    "csrf": "…", "logged_in": false, "handle": "guest"
  },
  "frame": { /* the MOTD Frame, see architecture.md */ }
}
```

### `POST /api/action`
One interaction. Body: `{ key?, input?, cmd?, data?, goto? }`.

| field | meaning |
|---|---|
| `key`   | a single keystroke: `"M"`, `"ENTER"`, `"ESC"`, `"UP"`, `" "` … |
| `input` | a completed line (line-input / game prompts) |
| `cmd`   | `"submit"` / `"cancel"` (forms), `"leave"` (chat), `"noop"` (redraw), `"goto"` |
| `data`  | form values `{ field: value }`; for a `file` field, `{name, b64}` |
| `goto`  | with `cmd:"goto"` — `board:<slug>` / `msg:<id>` / `user:<handle>` / `news:<cat>` / `game:<slug>` |

Returns a **Frame** (`architecture.md`).

### `GET /api/whoami`
Session/user summary + a re-render of the current frame (used on reconnect).

### `POST /api/auth/logout`
Ends the call, returns the goodbye Frame with `meta.hangup = true`.

### `GET /api/ticker`
`{ "lines": [ "one-liner…", "NEWS: headline…" ] }` for the crawl. Cached 30 s.

## Chat

| endpoint | |
|---|---|
| `GET  /api/chat/poll?since=<id>` | `{ messages, last, present, server }` |
| `POST /api/chat/say` `{body}` | posts a line (`/me …` supported); rate-limited 1/1.2 s |
| `GET  /api/chat/stream?since=<id>` | SSE (`event: msg` / `ping` / `bye`); ~45 s then reconnect |

## Files

`GET /api/file/{id}` — streams the file with `Content-Disposition: attachment`
after an RBAC (area rank) + `audit_log` check. `external_url` rows 302-redirect.

## Deep links (render the shell with entity `<meta>` + a `goto` hint)

`/b/{slug}` board · `/m/{id}` message · `/u/{handle}` profile ·
`/news/{cat}` wire · `/g/{slug}` game.

## SEO / misc

`/robots.txt` · `/sitemap.xml` (boards, news, games, ~2k messages, ~2k profiles) ·
`/manifest.webmanifest` · `/og/{slug}.png` (GD share card:
`default`, `msg-<id>`, `board-<slug>`, `user-<handle>`, `news-<cat>`, `game-<slug>`) ·
`/.well-known/security.txt`.

## Errors

JSON `{ "error": "…" }` with a fitting status: `403` cross-origin / permission,
`419` stale CSRF, `404` unknown route (`"NO CARRIER - route not found"`),
`500` `"CARRIER LOST"`.
