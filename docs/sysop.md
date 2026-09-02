# SysOp guide

The whole board is administered from inside the terminal. Log in with an
account of **rank ≥ 80** (`cosysop` or `sysop`) and the main menu grows a
`[#] SysOp Area` item. Deep link: `/#` → menu `sysop`.

## First login

The seeded `sysop` account is forced to change its password on first login. Its
initial password is whatever `sysop.password` is in `app/config.php` — it is
**never** stored in git.

## Roles & permissions

Roles carry a `rank` (0–100). Permissions are additive across every role a user
holds; `guest` (no account) gets what the `guest` role grants.

| role | rank | gist |
|---|--:|---|
| guest | 0 | read public boards & file lists |
| user | 10 | post, download, chat, vote, games, tickets *(default on register)* |
| elite | 30 | + upload files |
| cosysop | 80 | + most of the admin area (not global config / integrations / impersonate) |
| sysop | 100 | everything |

Change any of this in **SysOp → Users & Roles** (number toggles a role) and
**SysOp → Screens & Menus** (a menu item's `min_permission` / `min_role_rank`).

## Screens & menus are data

- **Screens** (`screens` table) are pipe-coloured (`|00`–`|15` fg, `|16`–`|23`
  bg) with `{{token}}` templating. Edit the body live; change `content_type` to
  `ansi` to paste real ECMA-48 art or `plain` for none.
- **Menus** (`menus` / `menu_items`): edit a row's label, hotkey, sort and
  enabled flag. `action` is one of `menu` / `screen` / `module` / `url` /
  `logoff` / `divider`; `target` is the slug it points at.

Tokens available to screens: `site_name site_tagline sysop_handle telnet_host
version baud nodes_total now node ip phone handle php_version host_uptime
users_total calls_total messages_total files_total oneliners_total last_caller`.

## Global config

**SysOp → Global Config** edits the `settings` table and applies immediately
(stats cache is busted on save). Notable keys:

| key | effect |
|---|---|
| `site_name`, `site_tagline`, `sysop_handle` | identity, everywhere |
| `nodes`, `baud` | "phone lines" and the CONNECT string |
| `registration_open`, `guest_browsing` | gate new users / guests |
| `crt_intensity`, `crt_scanlines`, `crt_flicker`, `crt_curvature` | CRT defaults |
| `sound_default` | is sound on for a fresh visitor |
| `motd_screen` | which screen slug greets a caller |
| `news_feeds_*` | RSS/Atom sources per category (one URL per line) |
| `discord_enabled`, `discord_events` | webhook master switch + allow-list |

## Discord

**SysOp → Discord Hooks**: add a webhook URL (must be `https://discord.com/api/webhooks/…`),
pick its events (csv or `*`), toggle, or **T** to send a test ping. Events:
`user.register`, `ticket.new`, `ticket.reply`, `message.new`, `sysop.page`.
Delivery is queued to beanstalkd (`bbs/discord`) or `jobs_log`; the worker sends
it.

## Audit & call log

Every state-changing action calls `AuditLog::record()` →
**SysOp → Audit Log** (paged, newest first). **SysOp → Call Log** is one row per
dial-in: handle, IP, the phone-number render, node, baud, seconds connected,
pages viewed.

## Moderation

- **Users & Roles**: `B` ban / un-ban, `U` suspend, `P` reset password (forces
  a change), `I` impersonate (audited; rank needs `admin.impersonate`).
- **Message Admin**: `D` soft-delete a message by id (kept for the audit trail).
- **File Admin**: upload queue — `A` approve, `X` reject.
- **Tickets**: `R` staff reply, `O/X/A/C` set status.

## Housekeeping

`contrib/maintenance.php` (nightly cron) expires dead sessions, closes orphan
call-log rows, prunes `login_attempts` / old `jobs_log`, and pings search
engines with the sitemap.
