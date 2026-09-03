# Hackers-MUD

A small persistent multiplayer MUD bolted onto the BBS. Reached from the
**Game Room → `[M] Hackers-MUD`**. It renders through the same CRT terminal as
the rest of the board (frame `view:game`, `mode:line`) - you type commands, it
prints pipe-coded lines back.

Your BBS account *is* your MUD character: the first time you jack in you pick a
background and a `mud_players` row is created, keyed on your `user_id`. Guests
cannot play (no account = no character).

The world is a cyberpunk Night City: **eight districts, ~128 rooms**, 200+ item
templates, 70+ enemy types, 20+ shops, 26 chained jobs plus a repeatable
bounty board, and ~45 readable room descriptions (`look <terminal|graffiti|
sign>`). Everything lives in `mud_*` tables.

**Audio**: the terminal plays synthesised effect sounds (footsteps, gunfire,
blades, hacking, hits, pickups, level-ups) and a looping ambient bed per zone
(rain / hum / drip / wind / static). `frame.meta.sfx` + `frame.meta.ambient`
carry these; toggle all sound with the HUD **SOUND** button.

## Setup

The schema ships as a migration; the world content is a separate seed script.

```
php mysql/migrate.php                # creates the mud_* tables (2026_09_03_02_mud.sql)
php mysql/mud_world.php              # builds / rebuilds the world (idempotent)
php mysql/mud_world.php --stats      # just print row counts
```

`mud_world.php` wipes and re-seeds the world/template tables. It **keeps**
`mud_players`, their skills and quest progress; it clears their equipment and
effects and moves them to the start room so no instance id can dangle.

### World tick

The world breathes on a "tick" (default 18s): mob respawns, wandering, HP/heat
regen, hunger/thirst decay, expiring buffs, and aggressive mobs jumping idle
players. It runs **lazily at the top of every player command**, so the MUD works
with no cron at all. For respawns/patrols while nobody is connected, also run:

```
* * * * *  /usr/bin/php <BBS>/contrib/mud-tick.php >> <BBS>/storage/logs/mud.log 2>&1
```

`contrib/mud-tick.php --loop` runs it as a foreground daemon instead.

## Playing

`help` in-game prints the full command list; `help combat` and `help hacking`
go deeper. `ESC` leaves the MUD (your character is saved); come back and you
resume where you stood.

### Commands

| group | commands |
|--|--|
| move | `n s e w u d ne nw se sw`, `enter`/`exit`, `go <dir>` |
| look | `look` (`l`), `look <thing>`, `examine`/`x <thing>`, `map` (`m`) |
| self | `score` (`sc`), `inventory` (`i`), `equipment` (`eq`), `who`, `feed`, `time` |
| items | `get`/`take`, `drop`, `get all`, `put <x> in <y>`, `give <x> to <y>` |
| gear | `wear`, `wield`, `hold`, `implant`, `remove` |
| use | `use`, `eat`, `drink`, `inject <stim>` |
| fight | `kill <e>` (`k`/`attack`), *(blank line)* to trade a round, `flee`, `consider <e>`, `hack <e>` |
| shop | `list`, `buy <x>`, `sell <x>`, `value <x>` |
| talk | `talk <npc> [about <topic>]`, `say <msg>` (`'`), `emote <msg>` (`:`) |
| jobs | `talk` to a fixer, `accept <job>`, `quests`; `board` at a job board for repeatable bounties |
| grow | `spend <stat>` (level-up points), `train <skill>` at a trainer NPC (costs eddies) |
| crime | `rob <npc>`, `hack atm|terminal|vending|camera|door`, `bribe` a bent fixer to clear NCPD heat |
| body | `rest`, `sleep`, `wake`, `sit`, `stand` |
| misc | `recall`/`home`, `deposit`/`withdraw <n>` (at a bank), `scan` (adjacent rooms), `uninstall <chrome>` at a ripperdoc |

### NCPD heat & death

Hacking ATMs, tripping alarms and killing cops raise a **wanted** level (shown
on the prompt and score sheet). It cools while you stay off the patrolled
streets (indoors, the Undercity, the Badlands); at 60+ a **MaxTac responder**
drops on you until you lose it, die, or `bribe` it away. On death, everything
you were *carrying* (not worn) stays in a body bag where you fell - get back
there before someone else does.

### Districts

Kabuki (start) · Watson Docks · Corpo Plaza · the Combat Zone · the Undercity,
plus **the Neon Kitsune** (a Tyger Claw arcade/BD den off Jig-Jig, reached via
the rooftops or Ping Alley) and **the Badlands Edge** (nomad camps, the Raffen
Shiv, a solar-farm ruin - out past the wall on the maglev), and a **Deep
Undercity** under the Cistern ending at a pre-war bunker. Four district bosses
plus mini-bosses (the Kitsune, a Militech VP, Skinner of the Raffen Shiv, a
fallout-shelter warden AI).

### Character

Five stats: **body, reflex, intel, cool, tech**. Three backgrounds set the
starting spread and kit:

| background | strong | starts with |
|--|--|--|
| Netrunner | intel / tech | light pistol, kevlar vest, Paraline deck, neural jack, ration bar |
| Solo | body / reflex | baseball bat, armoured jacket, ballistic longcoat, refurb deck, ration bar |
| Techie | tech | pipe wrench, tech goggles, tool harness, work boots, yakitori |

Kill things → XP → levels (`xpForLevel = round(80·lvl^1.9)`), +max HP/heat and
**3 stat points** per level (`spend <stat>`). Eight skills train themselves
through use (hacking, melee, firearms, stealth, athletics, …).

- **Heat** (energy) powers hacking; it regens over time, faster while
  `rest`/`sleep`, and stalls if hunger or thirst hit zero.
- **Implants** ("chrome") give permanent stat mods - `implant <item>`; removing
  chrome needs a ripperdoc (not yet in-world - choose carefully).
- **Buffs** from drugs/drink are pre-fight, timed (`Berserk`, `Reflex Surge`,
  `Focused`, `Ironhide`, `Dutch Courage`).
- **Death**: at 0 HP Trauma Team revives you at your safehouse minus 25% of the
  eddies on hand (bank is safe). Below 30% HP a fight auto-pauses so you can
  `flee` or `inject` a stim.

### Combat

`d20 + attackRating` vs `10 + defence`. `attackRating` = your body (or reflex
for ranged) + level + ½ weapon skill + active dmg buffs. A natural 20, or
beating the target number by 19+, crits for ×1.8. `kill` resolves several
rounds per command; press ENTER on a blank line to continue.

### Hacking

`hack <target>` uses `½(intel+tech) + hacking skill` (+deck bonus, −3 with no
cyberdeck) and costs 3 heat. Targets: locked/electronic **doors**, **terminals**
and **panels** (eddies + data shards), **vending machines** (free food),
**ATMs** (big money, high trace risk → an NCPD patrol homes in), **cameras**
(blind NCPD in the area), and an **enemy during a fight** (chip damage + a
damage buff). Botching a secure hack can spawn a patrol.

## The world

| zone | level | notes |
|--|--|--|
| Kabuki | 1-6 | start. Your coffin (safehouse), Jig-Jig Street, the market (Chrome Row, Ramen Row, The Gristle), the Afterlife (Rogue the fixer), a clinic. |
| Watson Docks | 3-10 | the Bazaar (black-market shops), container maze, Warehouse 7 (Maelstrom + boss **Royce**), piers, Smuggler's Rest. |
| Corpo Plaza | 5-16 | plaza, Arasaka Tower (lobby → mezzanine → server floor), Militech Boutique, NightCorp Bank, the Gold Room, a maintenance spine. |
| The Combat Zone | 9-20 | dead mall, Scav nest, boss **the Scav chief**, dark leisure level, rooftop. |
| The Undercity | 6-18 | drains, Old Metro, nomad camp, boss **the Rat King**, a buried server vault. |
| The Blackwall Fringe | 16-30 | Fringe datacentre, antenna field, a deep-dive jack point, cyberspace, final boss **THE CURATOR**. |

Zones connect: Kabuki ⇄ Watson ⇄ (checkpoint) Corpo ⇄ Combat Zone, and the
Undercity threads under all of them via drains; the Fringe is reached from a
comms conduit off the nomad camp.

### Jobs

Fixers give chained jobs (`mud_quests.next_vnum`). Rogue's line runs Bounce Test
→ Noodle Debt → Claws Off, then later Head of the Table and finally **The
Curator** (level 10). Ozob (nomad), the barge bartender and the tunnel tech
each have their own two-step chains. `talk <fixer> about job`, `accept <name>`,
then `talk` to them again once it shows complete to get paid (+ any reward gear,
+ the next job if you meet its level).

## Tables

All prefixed `mud_`:

`mud_config` · `mud_zones` · `mud_rooms` · `mud_exits` ·
`mud_item_templates` / `mud_item_instances` ·
`mud_mob_templates` / `mud_mob_instances` ·
`mud_shops` / `mud_shop_stock` ·
`mud_players` · `mud_player_equipment` / `mud_player_effects` /
`mud_player_skills` · `mud_quests` / `mud_player_quests` · `mud_events` ·
`mud_room_extras` (readable lore). `mud_players.wanted` is the NCPD heat.

The world seed is split: `mysql/mud_world.php` (the core) requires
`mysql/mud_world_ext.php` (the content expansion - the two extra zones, the
deep tunnels, the bulk of the items/mobs/quests, and the room lore). A rebuild
now **preserves** each player's level, skills, eddies, quests, location and
gear (snapshotted by vnum and restored after the wipe).

## Code

`app/src/Mud/` (namespace `Bbs\Mud`):

| file | role |
|--|--|
| `World.php` | data access for rooms/exits/items/mobs/shops + dice |
| `Player.php` | character: archetypes, derived stats, equipment, effects, skills, XP/level/death |
| `Combat.php` | turn-based `engage()`, aggro `mobStrike()`, `kill()` loot/XP |
| `Quests.php` | accept / progress / turn-in, quest chaining |
| `Render.php` | room view, ASCII minimap, score sheet, inventory, examine |
| `Tick.php` | the world heartbeat (`maybeRun()` lazy gate, `run()`, `queue()`/`drain()`) |
| `Mud.php` | command parser + all `cmd_*` handlers, `open()` / `chooseArchetype()` |

`app/src/Modules/MudModule.php` bridges it to the BBS engine (slug `mud.play`,
registered in `Engine::MODULES`); it keeps the scrollback in the module's
session sub-state and feeds each typed line to `Mud::command()`.
