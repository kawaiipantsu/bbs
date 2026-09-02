# Features & hotkeys

Everything is keyboard-driven. Type the letter in `[brackets]`, or use
`↑ ↓` + `ENTER`; `ESC` / `Q` backs out; `B` / `SPACE` page a long screen;
`Ctrl-S` toggles sound; `Ctrl-L` redraws.

## Connect

1. Power-on → BIOS POST → `ATZ` / `ATDT <your-ip-as-a-phone-number>`.
2. Synthesised modem: dial tone, DTMF of your "number", ring, carrier, handshake.
3. `CONNECT 57600` → auto-types `telnet bbs.thugs.red` → MOTD.
4. **L** log in · **N** new user · **G** guest (read-only public areas) · **Q** hang up.

## Main menu

| key | area | |
|--|--|--|
| **M** | Message Base | conferences → boards → threads → read/post/reply |
| **F** | File Libraries | areas, browse, download, upload (SysOp-approved), text-file library |
| **N** | News Wire | IT / Hacking / Tech / Entertainment — live RSS, ENTER opens the link |
| **C** | Communication | node chat, one-liner wall, user list, who's online, comment to SysOp |
| **G** | Game Room | Hackers-MUD (persistent multiplayer) + 16 door games + hall of fame |
| **V** | Voting Booth | one vote per account, live ANSI bar graph |
| **I** | System Information | board identity, software, live stats |
| **S** | Statistics | totals, top posters, busiest boards, 7-day call graph |
| **T** | SysOp Ticket | file a ticket, read staff replies |
| **W** | Who / SysOps | staff roster |
| **A** | Account | your profile, signature, e-mail (encrypted), password |
| **#** | SysOp Area | *(rank ≥ 80)* board administration |
| **?** | Help · **O** Goodbye |

## Messages

Browse boards, read threads (`N`/`P` between messages), **P** to post, **R** to
reply (quoted subject). **Scan** shows unread-since-last-call per board;
**Find** is a MySQL FULLTEXT search across every board you can read. Each
message shows the poster's IP rendered as `Calling from: (415) 203-1174`.

## Files

Areas gate on role rank. Details screen shows size, SHA-256, uploader,
downloads. **U** uploads (a real file picker; held for approval unless you're
staff, 16 MB cap). Downloads stream through PHP with an RBAC + audit check —
the `storage/` tree is never web-served. The **Library** area holds curated
links/text instead of blobs.

## Games

### Hackers-MUD

A persistent multiplayer MUD in a cyberpunk Night City — six districts, ~86
rooms, ~120 items, ~40 enemy types, 13 shops, 12 chained fixer jobs, a live
world tick (respawns / wander / regen / aggro), ASCII minimap, character sheet,
implants, buffs, hacking, robbery, banking. Your BBS account is your character.
Full write-up in [`docs/mud.md`](mud.md).

### Door games

`Guess The Number`, `Hangman`, `Ten Thousand` (dice), `One-Armed Bandit`
(blackjack), `Hunt The Wumpus`, `Legend of the Red Console` (LORD-lite),
`Rock-Paper-Scissors`, `Tic-Tac-Toe`, `21 Matchsticks` (Nim), `Mastermind`,
`Anagram`, `Hi-Lo`, `Craps`, `Minesweeper`, `2048`, `Lights Out` — 16 in all.
Scores land in the hall of fame.

## Chat

Real-time-ish node chat (long-poll, 1.8 s; Redis pub/sub wakes the SSE variant
faster where the proxy allows it). `/me` actions, presence list, join/leave
lines. `ESC` leaves.

## SysOp / Admin area

Same terminal, `permission`-gated per screen, every write to `audit_log`:

- **Users & Roles** — search, toggle roles, ban/suspend, reset password,
  impersonate (audited).
- **Message / File / News / Poll admin** — CRUD, upload approval queue, force a
  news refresh, open/close polls.
- **Screens & Menus** — edit any ANSI screen body and the menu tree live.
- **Global Config** — every `settings` row, applied immediately.
- **Discord Hooks** — add/toggle/test webhooks; pick which events fire.
- **Tickets** — staff replies + status workflow.
- **Audit Log** — paged, every state change across the board.
- **Call Log** — every "dial-in": IP, phone-number render, node, baud,
  duration, pages viewed.

## The monitor itself

The **brightness** and **contrast** knobs are cut from the photo and really
turn (drag / scroll / arrow keys). Brightness drives screen brightness;
**contrast drives colour saturation** — wind it all the way down for a
black-and-white monochrome CRT, up for punchy phosphor. The **power button**
switches the CRT off with a collapse effect + relay clunk; any key or click
powers it back on. Preferences persist in `localStorage`.
