# Security notes

## Threat model

Public internet site behind Cloudflare + a WAF. Anyone can register with a weak
password (by design). Assume hostile input in every message body, handle,
one-liner, ticket, chat line and form field. The prize for an attacker is
SysOp access or the encrypted PII.

## Controls

### Transport / headers
- Front controller sets, on every dynamic response: `Content-Security-Policy`
  (`default-src 'self'`, `script-src 'self'` — **no inline JS**,
  `connect-src 'self'`, `frame-ancestors 'none'`, `base-uri 'self'`,
  `form-action 'self'`, `img/media` allow `data:`/`blob:`), `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy`
  (geo/mic/cam/usb/payment off), `COOP`/`CORP: same-origin`.
- `html/.htaccess` repeats the static-asset headers and denies dotfiles and
  `*.sql|sh|md|ini|log|bak|…`; `RedirectMatch 404` on `/app|/mysql|/docs|
  /contrib|/assets|/vendor` (defence in depth — `app/` is already outside the
  web root).
- No HTTPS redirect here (TLS is upstream; a redirect would loop). Scheme comes
  from `X-Forwarded-Proto` for the `Secure` cookie flag and canonical URLs.

### Cross-origin
- State-changing verbs are refused if `Origin` is present and not the canonical
  host.
- CSRF: HMAC of the session id (`crypto.csrf_salt`), required as `X-CSRF` on
  every POST/PUT/DELETE and every chat send. A brand-new session's first POST is
  allowed (it has a valid token already).

### Sessions
- 256-bit random id, server-side row + Redis cache, `HttpOnly` + `SameSite=Lax`
  + `Secure`(when https). Rotated on login and on privilege change
  (impersonate). Idle TTL 1 h, absolute 7 d. Optionally bound to the caller's
  `/24` (v4) / `/64` (v6) via an HMAC (`session.bind_network`).

### Auth
- `password_hash` bcrypt cost 12; `password_needs_rehash` upgrade on login.
  Minimum length 3, no complexity rule — *weak passwords are a feature*, so the
  mitigation is rate-limiting, not policy.
- `login_attempts` sliding window: 30 fails / IP / 15 min, 10 / handle / 15 min.
  Generic "Bad handle or password." for every failure reason.

### Injection
- **SQL**: PDO prepared statements everywhere; `Db::insert/update` build
  parameterised statements from array keys — no user string is concatenated into
  SQL. The two `LIKE` searches escape `% _`.
- **XSS**: the client never receives HTML for board content. The server sends
  *styled runs*; `terminal.js` only ever creates text nodes inside `<span>`.
  The HTML shell escapes every interpolated value (`View::e`). JSON-LD is
  `json_encode`d.
- **Pipe/ANSI injection**: `{{token}}` values have `|` and `ESC` stripped before
  rendering, so a handle like `|09evil` can't recolour a screen.

### Crypto / PII
- `user_secrets` (email, real name, sysop notes) encrypted with libsodium
  `crypto_secretbox`; key in `app/config.php` (0640, gitignored). A per-value
  HMAC **blind index** lets the SysOp look up an email without bulk-decrypting.

### Files
- Uploads land in `./storage/files/` (outside the web root, mountable
  `noexec,nodev`). Served only through `ApiController::download()` after an area
  rank check + `audit_log` write. Path is `sha256`-namespaced; filename is
  sanitised to `[A-Za-z0-9._-]`. 16 MB cap. Non-staff uploads are quarantined
  (`is_approved = 0`) until a SysOp approves them.

### Background jobs
- The beanstalkd server is shared; every tube name is forced to the `bbs/`
  prefix. The worker only acts on a fixed set of tubes and never `eval`s a
  payload.

### Audit
- `audit_log` is append-only from the app (no update/delete path in the UI).
  Every admin mutation, auth event, upload, download, vote and post writes a
  row with actor, IP, the phone-number render, action, target and a JSON `meta`.

## Known limitations / accepted risk

- Weak passwords + optional network binding ⇒ session theft on a shared NAT is
  possible; acceptable for a hobby BBS, tunable via `session.bind_network`.
- Chat/long-poll holds a PHP worker briefly; capped at ~45 s and rate-limited.
- No CAPTCHA on registration — rate-limiting + SysOp ban is the response.
- `og` images are user-influenced text drawn with GD; they are not a trust
  surface (no SSRF, fixed templates).

## Reporting

`/.well-known/security.txt` → `mailto:sysop@thugs.red`. Please describe the
*class* of issue, not a working exploit chain.
