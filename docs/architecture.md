# Architecture

No framework. PHP 8.2, PDO, a ~120-line router, a hand-rolled PSR-4 autoloader.
The browser is a **thin terminal**; the server owns all BBS logic.

```
Browser (CRT + terminal, ES modules)
  │  POST /api/session         ── "the call connects"
  │  POST /api/action {key…}   ── one keystroke / form submit / command
  ▼
html/index.php  (front controller: security headers, CORS guard, Router)
  ▼
Bbs\Bbs\Engine  (state machine; navigation stack in the session)
  ├── Menu      ← menus / menu_items          (DB)
  ├── Screen    ← screens                     (DB, pipe/ANSI colour)
  ├── Module[]  ← Messages/Files/News/Games/Chat/Poll/Ticket/Stats/
  │               Community/Account/Admin
  └── AnsiRenderer → styled "runs" as JSON → terminal.js paints the grid
```

## Request lifecycle

1. Apache `mod_rewrite` (`html/.htaccess`) sends every non-file request to
   `html/index.php`.
2. `bootstrap.php` loads config, registers the autoloader and error handlers,
   and defines `BBS_STORAGE` (the out-of-webroot `./storage` tree).
3. `index.php` sets a strict CSP and the other security headers, refuses
   cross-origin POSTs, and dispatches via `Bbs\Core\Router`.
4. `ApiController::session()` starts a `Session` (row in `sessions`, mirrored in
   Redis), writes a `call_log` row, and returns the connection facts + the first
   frame (the MOTD screen).
5. `ApiController::action()` verifies the CSRF token, builds an `Engine` from the
   session, and calls `Engine::dispatch($input)`. The engine walks its
   navigation stack (`motd → auth → menu → module → …`), mutates state, and
   returns a **Frame**.

## The Frame (server → client contract)

```jsonc
{
  "view": "menu|screen|form|chat|redirect",
  "title": "Main Menu",
  "prompt": "Command",
  "mode": "menu|pager|form|line|game|chat|redirect",
  "sound": "beep|bell|error|connect|hangup|null",
  "fields": [ /* form mode: {name,label,type,max,options,value} */ ],
  "meta":  { "items": [ /* menu items for arrow-nav */ ], "hangup": false },
  "lines": [ [ {"s":"text","f":7,"b":0,"o":false,"k":false} ] ],
  "csrf": "…",
  "whoami": { "guest": true, "handle": "guest", "rank": 0, "node": 3, "phone": "(…) …" }
}
```

`lines` is an array of rows; each row is an array of **runs**
(`s`tring, `f`g 0-255, `b`g 0-15, b`o`ld, blin`k`). `terminal.js` renders each
run as a `<span class="fN bM …">`.

## Front-end modules (`html/js/`)

| file | role |
|---|---|
| `app.js` | boot orchestration, HUD, ticker, global keydown → `term.key()` |
| `boot.js` | power-on, BIOS POST, `ATDT`, modem handshake, `telnet …` type-out |
| `audio.js` | WebAudio synthesis: key clicks, bell, DTMF dial, carrier, power thunk |
| `terminal.js` | grid renderer + input handling per mode (menu/pager/line/form/game) |
| `controls.js` | the cut-out knobs (brightness/contrast) and power button |
| `chat.js` | node-chat client (long-poll `/api/chat/poll`) |
| `net.js` | `fetch` wrappers, CSRF token plumbing |

## State & storage

- **Sessions**: `sessions` table + Redis cache (`bbs:sess:<id>`, 60s TTL). Cookie
  `bbs_node`, HttpOnly, SameSite=Lax, `Secure` when `X-Forwarded-Proto=https`.
- **Navigation stack**: JSON in `sessions.data` under key `bbs`
  (`{stack:[{t,ref,st}]}`).
- **Files/media**: `./storage/` (outside the web root; mount a disk there).
  Served only through `ApiController::download()` with an RBAC + audit check.
- **Jobs**: beanstalkd tubes `bbs/discord|mail|news|system`; if it is
  unreachable, `Bbs\Core\Queue` falls back to the `jobs_log` table and
  `contrib/worker.php --once` (cron) drains it.

## Why this shape

- The server rendering every screen keeps game logic, permissions and the audit
  trail impossible to bypass from the client.
- Sending *styled runs* instead of HTML means the client can never be tricked
  into executing markup from a message body — the renderer only ever creates
  `<span>` text nodes.
- Menus-as-data means the SysOp screen can rebuild the board live.
