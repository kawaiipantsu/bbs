# Changelog

All notable changes to THUGS(red) BBS.

## [1.0.0] — 2026-09-02

Initial release.

### Terminal & CRT
- 132×50 character grid, scaled to fill the monitor glass; CP437 web font.
- CRT monitor frame (`terminal_monitor.png`) fills the window on any aspect
  ratio with seamless black letterboxing.
- CRT effects: scanlines, aperture shimmer, vignette, phosphor glow, flicker,
  rolling refresh bar, power-on collapse/expand.
- Working **brightness** knob and **contrast** knob (= colour saturation,
  down to monochrome), cut from the photo; working **power button** with
  collapse effect + relay-clunk sound.
- Fully synthesised WebAudio: key clicks, cursor blips, bell, DTMF dialing of
  the caller's IP-as-phone-number, dial tone, ring, V-series carrier + training
  handshake, flyback whine.
- Boot sequence: power-on → BIOS POST → `ATZ` / `ATDT` → modem → `CONNECT` →
  auto-typed `telnet bbs.thugs.red`.

### BBS
- Data-driven menus and screens (pipe colour + `{{token}}` templating), editable
  live in the SysOp area.
- Messages: conferences → boards → threads, post/reply, scan-new, FULLTEXT find,
  per-user read marks, sender IP shown as a phone number.
- Files: rank-gated libraries, SHA-256, audited streamed downloads, upload +
  approval queue, reference-link library.
- News wire: live RSS/Atom for IT / Hacking / Tech / Entertainment.
- Games: Guess The Number, Hangman, Ten Thousand, Blackjack, Hunt The Wumpus,
  Legend of the Red Console; hall of fame.
- Node chat (long-poll + optional SSE), one-liner wall, voting booth, user list,
  who's-online, stats, SysOp tickets.

### Admin / ops
- RBAC (guest/user/elite/cosysop/sysop) with a permission catalogue.
- SysOp area: users & roles, content CRUD, screen/menu editor, global config,
  Discord webhooks, audit log, call log.
- libsodium-encrypted PII (`user_secrets`) with an HMAC blind index.
- Background worker (systemd or cron) for Discord / news / mail; DB job-queue
  fallback when beanstalkd is absent.
- `.htaccess` + front-controller security headers (strict CSP, no inline JS),
  cross-origin POST guard, CSRF on every mutation.
- SEO: OpenGraph + Twitter + JSON-LD, `sitemap.xml`, `robots.txt`,
  `manifest.webmanifest`, GD-rendered per-entity `/og/*.png`, fancy deep links.
- Docker Compose stack for local/third-party use.
- Identity asset generator (`assets/generate.php`).
