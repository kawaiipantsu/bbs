# Technology

| layer | choice | why |
|---|---|---|
| Language | **PHP 8.2** (strict types) | on the host, fast enough, `sodium`/`gd`/`pdo` built in |
| Framework | **none** | ~1.5k LOC of core; a router + autoloader is the whole "framework" |
| DB | **MariaDB / PDO** | the brief: the whole board lives in the DB; FULLTEXT for search |
| Cache / pubsub | **Redis** (optional) | session cache, chat fan-out; app degrades to DB without it |
| Queue | **beanstalkd** (optional) | Discord/news/mail jobs; DB `jobs_log` fallback + cron |
| Crypto | **libsodium** `crypto_secretbox` | encrypt PII in `user_secrets`; HMAC for CSRF + blind index |
| Passwords | `password_hash` **bcrypt** cost 12 | weak passwords *allowed* (BBS nostalgia), still properly hashed |
| Front-end | **vanilla ES modules**, Canvas-free | a `<span>` grid is crisp at 132×50 and immune to markup injection |
| Font | **DejaVu Sans Mono**, CP437-subset → woff2 (53 KB) | 100 % box-drawing / block / shade coverage; self-hosted, no CDN |
| Audio | **WebAudio**, fully synthesised | DTMF, carrier, handshake, key clicks, flyback whine — zero sample files |
| Effects | CSS (`crt.css`) | scanlines, vignette, flicker, roll bar, barrel curvature, power-on collapse |

## Build / deploy

There is **no build step**. Edit a file, it's live. `assets/generate.php`
(GD) regenerates the favicon/OG/wallpaper set from the palette. `mysql/migrate.php`
applies schema + `mysql/migrations/*.sql`.

## Notable implementation details

- **Terminal sizing**: monospace ⇒ `charWidth = k · fontSize`. Measure `k` once,
  then pick `fontSize` so `cols` cells span the glass and an independent
  `line-height` so `rows` lines span its height. Non-square cells give the
  stretched-CRT look for free. No `transform: scale`.
- **Pipe colour**: screens store `|00`–`|15` foreground, `|16`–`|23` background,
  plus `{{token}}` templating (`{{phone}}`, `{{site_name}}`, live stats…).
  `AnsiRenderer` also parses a safe subset of real ECMA-48 SGR for imported art.
- **Phone number from IP**: deterministic `(AAA) EEE-LLLL` from the octets +
  `crc32`; IPv6 folds to 10 digits. Cosmetic only.
- **CSP**: `script-src 'self'` (no inline JS anywhere), `connect-src 'self'`,
  `frame-ancestors 'none'`; audio/fonts are `data:`/`blob:` + self.
