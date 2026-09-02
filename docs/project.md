# THUGS(red) BBS — Project

## What it is

A **bulletin board system** delivered as a website. It looks and behaves like you
dialled into a real serial/modem BBS in 1992: a CRT monitor fills the browser
window, you watch an AT-command modem handshake connect, then `telnet
bbs.thugs.red` auto-types and you land in a keyboard-driven, ANSI-coloured,
256-colour terminal that lives entirely inside the monitor glass.

Everything the board *is* — menus, screens, message areas, files, users, roles,
config, the audit trail — lives in MariaDB and is editable from the in-terminal
SysOp area. There is no separate CMS and no build step.

## Goals

- **Feel real.** CRT frame, scanlines, flicker, phosphor glow, synthesised modem
  and key-click audio, working brightness/contrast knobs and a power button.
- **Be a real BBS.** Conferences → boards → threads, file libraries with an
  upload/approval queue, door games, node chat, one-liner wall, voting booth,
  a live news wire, SysOp tickets, user list, stats.
- **Be data-driven.** The menu tree and every screen are rows in the database.
  A SysOp reshapes the board without touching code.
- **Be operable.** RBAC with encrypted PII, a full audit log, a call log that
  renders each visitor's IP as a phone number, Discord webhooks, background
  worker, sitemap/SEO, `.htaccess` hardening.

## Non-goals

- Not a federated/FidoNet node (the schema leaves room; it is not wired).
- Not a mobile-first app. It targets a keyboard and a wide screen; it degrades
  but the point is the big-terminal experience.

## Status

v1.0.0 — all listed areas are implemented and live at
<https://bbs.thugs.red>. See [features.md](features.md) for the hotkey map and
[architecture.md](architecture.md) for how a keystroke becomes a screen.

## Roadmap ideas

- Real ANSI (`.ans`, CP437) upload + viewer in the file areas.
- FidoNet-style message import/export.
- Per-conference themes (palette + CRT intensity).
- OAuth-free "callback verification" gag for new users.
- More door games; inter-node game challenges.
