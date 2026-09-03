<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * Structured (JSON-friendly) view of the MUD for the standalone graphical
 * client at /hackers-mud. The BBS terminal keeps using pipe-coded lines via
 * MudModule; this class turns the same world state into objects a canvas
 * renderer can draw.
 */
final class Api
{
    /** Run one command and return its output lines + queued sound effects. */
    public static function run(int $playerId, string $cmd): array
    {
        Mud::takeSfx();
        $lines = Mud::command($playerId, $cmd);
        return ['lines' => array_values($lines), 'sfx' => Mud::takeSfx()];
    }

    /** First-time entry: create the character for an archetype choice. */
    public static function chooseArchetype(int $userId, string $handle, string $choice): array
    {
        Mud::takeSfx();
        $res = Mud::chooseArchetype($userId, $handle, $choice);
        $res['sfx'] = Mud::takeSfx();
        return $res;
    }

    /* ---- the big snapshot ------------------------------------------- */

    /** @param list<string> $log recent log lines the client keeps */
    public static function snapshot(int $playerId, array $log = []): array
    {
        $p = Player::byId($playerId);
        if (!$p) {
            return ['ok' => false, 'error' => 'no character'];
        }
        Tick::maybeRun();
        // drain any tick-generated messages into the log
        $pending = Tick::drain($p);
        if ($pending) {
            $log = array_merge($log, $pending);
            $p = Player::byId($playerId);
        }

        $room = World::room((int) $p['room_id']);
        $stats = Player::effectiveStats($p);
        $base = json_decode($p['stats'] ?? '{}', true) ?: [];
        $need = Player::xpForLevel((int) $p['level'] + 1);
        $prev = Player::xpForLevel((int) $p['level']);
        $have = (int) $p['xp'];
        $xpPct = $need > $prev ? max(0, min(1, ($have - $prev) / ($need - $prev))) : 1;

        $skills = [];
        foreach (Db::all('SELECT skill, level FROM mud_player_skills WHERE player_id = ?', [$playerId]) as $s) {
            $skills[$s['skill']] = (int) $s['level'];
        }
        $effects = [];
        foreach (Player::effects($playerId) as $e) {
            $effects[] = ['name' => $e['name'], 'secs' => max(0, strtotime($e['expires_at']) - time())];
        }

        $data = json_decode($p['data'] ?? '{}', true) ?: [];
        $bounty = $data['bounty'] ?? null;

        [$phase, $tlabel] = Mud::daylight();

        return [
            'ok'      => true,
            'player'  => [
                'name'      => $p['name'],
                'archetype' => $p['archetype'],
                'title'     => $p['title'] ?: null,
                'level'     => (int) $p['level'],
                'xp'        => $have,
                'xpNext'    => $need,
                'xpPct'     => round($xpPct, 3),
                'hp'        => (int) $p['hp'],
                'maxHp'     => (int) $p['max_hp'],
                'energy'    => (int) $p['energy'],
                'maxEnergy' => (int) $p['max_energy'],
                'money'     => (int) $p['money'],
                'bank'      => (int) $p['bank'],
                'cred'      => (int) $p['street_cred'],
                'wanted'    => Player::wanted($p),
                'pos'       => $p['pos'],
                'state'     => $p['state'],
                'hunger'    => (int) $p['hunger'],
                'thirst'    => (int) $p['thirst'],
                'kills'     => (int) $p['kills'],
                'deaths'    => (int) $p['deaths'],
                'unspent'   => (int) $p['unspent_points'],
                'carry'     => round(Player::carryWeight($playerId), 1),
                'maxCarry'  => round(Player::maxCarry($p), 0),
                'stats'     => array_map('intval', $stats),
                'baseStats' => array_map(static fn ($k) => (int) ($base[$k] ?? $stats[$k]), array_combine(Player::STATS, Player::STATS)),
                'skills'    => $skills,
                'effects'   => $effects,
                'ac'        => Player::armorClass($p),
            ],
            'room'      => self::room($p, $room),
            'inventory' => self::inventory($playerId),
            'equipment' => self::equipment($playerId),
            'quests'    => self::quests($playerId),
            'bounty'    => $bounty ? [
                'name' => $bounty['name'], 'have' => (int) ($bounty['have'] ?? 0),
                'need' => (int) $bounty['need'], 'reward' => (int) $bounty['reward'],
                'done' => (int) ($bounty['have'] ?? 0) >= (int) $bounty['need'],
            ] : null,
            'map'      => self::map($p, $room),
            'log'      => array_values(array_slice($log, -120)),
            'ambient'  => $room ? Mud::ambientFor((int) $room['id']) : 'room',
            'time'     => ['phase' => $phase, 'label' => preg_replace('/\|\d\d/', '', $tlabel)],
            'online'   => self::online($playerId),
            'unread'   => (int) Db::val('SELECT COUNT(*) FROM mud_messages WHERE to_id = ? AND read_at IS NULL', [$playerId]),
        ];
    }

    /* ---- social: who's online + in-game SMS ----------------------- */

    /** @return list<array> players active in the last 15 minutes */
    public static function online(int $exceptId = 0): array
    {
        $out = [];
        $rows = Db::all(
            "SELECT p.id, p.name, p.level, p.archetype, p.title, p.last_cmd_at,
                    z.name AS zone
             FROM mud_players p
             LEFT JOIN mud_rooms r ON r.id = p.room_id
             LEFT JOIN mud_zones z ON z.id = r.zone_id
             WHERE p.last_cmd_at > NOW() - INTERVAL 15 MINUTE
             ORDER BY p.last_cmd_at DESC LIMIT 40"
        );
        foreach ($rows as $r) {
            $out[] = [
                'id'    => (int) $r['id'],
                'me'    => (int) $r['id'] === $exceptId,
                'name'  => $r['name'],
                'level' => (int) $r['level'],
                'archetype' => $r['archetype'],
                'title' => $r['title'] ?: null,
                'where' => $r['zone'] ?: 'the grid',
                'idle'  => max(0, time() - strtotime((string) $r['last_cmd_at'])),
            ];
        }
        return $out;
    }

    /** @return list<array> recent DM thread for this player */
    public static function inbox(int $playerId, int $limit = 40): array
    {
        $rows = Db::all(
            'SELECT id, from_id, from_name, to_id, body, created_at, read_at
             FROM mud_messages WHERE from_id = ? OR to_id = ?
             ORDER BY id DESC LIMIT ?',
            [$playerId, $playerId, $limit]
        );
        $out = [];
        foreach (array_reverse($rows) as $m) {
            $out[] = [
                'id'    => (int) $m['id'],
                'mine'  => (int) $m['from_id'] === $playerId,
                'from'  => $m['from_name'],
                'to_id' => (int) $m['to_id'],
                'body'  => $m['body'],
                'at'    => date('H:i', strtotime((string) $m['created_at'])),
                'unread' => (int) $m['to_id'] === $playerId && $m['read_at'] === null,
            ];
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string} */
    public static function sendSms(int $fromId, string $fromName, string $toName, string $body): array
    {
        $body = trim(mb_substr($body, 0, 280));
        if ($body === '') {
            return ['ok' => false, 'error' => 'Empty message.'];
        }
        $to = Db::one('SELECT id, name FROM mud_players WHERE LOWER(name) = LOWER(?)', [trim($toName)]);
        if (!$to) {
            return ['ok' => false, 'error' => 'No runner by that name.'];
        }
        if ((int) $to['id'] === $fromId) {
            return ['ok' => false, 'error' => "Texting yourself? It's been that kind of night."];
        }
        // light rate limit: 8 sends / minute
        $recent = (int) Db::val('SELECT COUNT(*) FROM mud_messages WHERE from_id = ? AND created_at > NOW() - INTERVAL 60 SECOND', [$fromId]);
        if ($recent >= 8) {
            return ['ok' => false, 'error' => 'Slow down - the network is throttling you.'];
        }
        Db::insert('mud_messages', [
            'from_id' => $fromId, 'from_name' => $fromName, 'to_id' => (int) $to['id'],
            'body' => $body, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        // let the recipient see it on their next tick
        Tick::queue((int) $to['id'], ['|13[SMS] ' . $fromName . ': |07' . $body]);
        // keep the table trimmed
        Db::q('DELETE FROM mud_messages WHERE created_at < NOW() - INTERVAL 3 DAY');
        return ['ok' => true, 'to' => $to['name']];
    }

    public static function markRead(int $playerId): void
    {
        Db::q('UPDATE mud_messages SET read_at = NOW() WHERE to_id = ? AND read_at IS NULL', [$playerId]);
    }

    /** Rich display card for an item template - shared by the itemdex
     *  showcase and the in-game inventory / gear panels so every item the
     *  player can touch carries its full stats, mods and description. */
    private static function tplCard(array $t): array
    {
        // stat_mods / effect arrive as a JSON string from a raw template row,
        // but already decoded to an array when the template came through
        // World::itemTemplate() (inventory / equipment).
        $mods = $t['stat_mods'] ?? null;
        if (is_string($mods)) {
            $mods = $mods !== '' ? json_decode($mods, true) : null;
        }
        $eff = $t['effect'] ?? null;
        if (is_string($eff)) {
            $eff = $eff !== '' ? json_decode($eff, true) : null;
        }
        return [
            'name'   => $t['name'],
            'icon'   => self::itemIcon($t),
            'type'   => $t['type'],
            'slot'   => ($t['slot'] ?? '') ?: null,
            'weight' => (float) ($t['weight'] ?? 0),
            'value'  => (int) ($t['value'] ?? 0),
            'dmg'    => ($t['damage_dice'] ?? '') ?: null,
            'armor'  => (int) ($t['armor'] ?? 0),
            'lvl'    => (int) ($t['level_req'] ?? 0),
            'flags'  => (string) ($t['flags'] ?? ''),
            'mods'   => $mods ?: null,
            'eff'    => $eff ? array_keys($eff) : null,
            'desc'   => (string) ($t['long_desc'] ?? ''),
        ];
    }

    /** Public item catalogue for the /hackers-mud/items showcase. */
    public static function itemdex(): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM mud_item_templates ORDER BY vnum') as $t) {
            $out[] = ['vnum' => (int) $t['vnum']] + self::tplCard($t);
        }
        return $out;
    }

    /* ---- room ------------------------------------------------------- */

    private static function room(array $p, ?array $room): array
    {
        if (!$room) {
            return ['name' => 'Nowhere', 'desc' => 'A bug. Type recall.', 'exits' => [], 'items' => [], 'mobs' => [], 'players' => []];
        }
        $dark = str_contains($room['flags'], 'dark') && !Render::hasLight($p);
        $zone = (string) Db::val('SELECT slug FROM mud_zones WHERE id = ?', [$room['zone_id']]);

        $exits = [];
        foreach (World::exits((int) $room['id']) as $dir => $x) {
            $exits[] = [
                'dir'      => $dir,
                'to'       => (int) $x['to_room'],
                'name'     => $x['to_name'],
                'locked'   => (bool) $x['locked'],
                'hidden'   => (bool) $x['hidden'],
                'keyword'  => $x['keyword'] ?: null,
                'hackable' => (int) $x['hack_dc'] > 0,
            ];
        }

        $items = [];
        if (!$dark) {
            foreach (World::items('room', (int) $room['id']) as $it) {
                $items[] = [
                    'id'   => (int) $it['id'],
                    'name' => $it['tpl']['name'],
                    'icon' => self::itemIcon($it['tpl']),
                    'kw'   => explode(' ', $it['tpl']['keywords'])[0],
                ];
            }
        }

        $mobs = [];
        if (!$dark) {
            foreach (World::mobs((int) $room['id']) as $mi) {
                if ($mi['state'] === 'dead') {
                    continue;
                }
                $t = $mi['tpl'];
                $behav = (string) $t['behavior'];
                $mobs[] = [
                    'id'        => (int) $mi['id'],
                    'name'      => $t['name'],
                    'short'     => $t['room_desc'],
                    'level'     => (int) $t['level'],
                    'hpPct'     => round((int) $mi['hp'] / max(1, (int) $t['max_hp']), 2),
                    'state'     => $mi['state'],
                    'sprite'    => self::mobSprite($t),
                    'faction'   => $t['faction'],
                    'hostile'   => str_contains($behav, 'aggressive'),
                    'boss'      => str_contains((string) $t['flags'], 'boss') || str_contains((string) $t['flags'], 'hunter'),
                    'kw'        => explode(' ', $t['keywords'])[0],
                    'shop'      => str_contains($behav, 'shopkeeper'),
                    'trainer'   => str_contains($behav, 'trainer:'),
                    'ripperdoc' => str_contains($behav, 'ripperdoc'),
                    'questgiver' => str_contains($behav, 'questgiver'),
                    'talk'      => (bool) ($t['dialogue'] ?? null),
                ];
            }
        }

        $players = [];
        foreach (World::players((int) $room['id'], (int) $p['id']) as $op) {
            $players[] = ['name' => $op['name'], 'title' => $op['title'] ?: null,
                          'level' => (int) $op['level'], 'archetype' => $op['archetype']];
        }

        $shop = World::shop((int) $room['id']);

        return [
            'vnum'    => (int) $room['vnum'],
            'id'      => (int) $room['id'],
            'name'    => $room['name'],
            'desc'    => $dark ? 'It is pitch black. You can barely see your own hands.' : $room['description'],
            'zone'    => $zone,
            'theme'   => self::zoneTheme($zone),
            'flags'   => $room['flags'],
            'dark'    => $dark,
            'safe'    => str_contains($room['flags'], 'safe'),
            'indoors' => str_contains($room['flags'], 'indoors'),
            'board'   => str_contains($room['flags'], 'board'),
            'bank'    => str_contains($room['flags'], 'bank'),
            'x'       => (int) $room['x'],
            'y'       => (int) $room['y'],
            'z'       => (int) $room['z'],
            'exits'   => $exits,
            'items'   => $items,
            'mobs'    => $mobs,
            'players' => $players,
            'shop'    => $shop ? [
                'name'     => $shop['name'],
                'greeting' => $shop['greeting'],
                'buys'     => trim((string) $shop['buy_types']) === '*'
                    ? ['*']
                    : array_values(array_filter(array_map('trim', explode(',', (string) $shop['buy_types'])))),
                'markdown' => (float) $shop['buy_markdown'],
            ] : null,
            'extras'  => array_map(static fn ($e) => explode('|', $e['keywords'])[0], World::roomExtras((int) $room['id'])),
        ];
    }

    private static function inventory(int $playerId): array
    {
        $grouped = [];
        foreach (Player::inventory($playerId) as $i) {
            $key = $i['tpl']['vnum'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id'      => (int) $i['id'],
                    'vnum'    => (int) $i['tpl']['vnum'],
                    'kw'      => explode(' ', $i['tpl']['keywords'])[0],
                    'qty'     => 0,
                    'illegal' => str_contains((string) $i['tpl']['flags'], 'illegal'),
                ] + self::tplCard($i['tpl']);
            }
            $grouped[$key]['qty']++;
        }
        return array_values($grouped);
    }

    private static function equipment(int $playerId): array
    {
        $out = [];
        foreach (Player::equipment($playerId) as $eq) {
            $out[$eq['slot']] = [
                'id'   => (int) $eq['id'],
                'vnum' => (int) $eq['tpl']['vnum'],
                'kw'   => explode(' ', $eq['tpl']['keywords'])[0],
            ] + self::tplCard($eq['tpl']);
        }
        return $out;
    }

    private static function quests(int $playerId): array
    {
        $out = [];
        foreach (Quests::active($playerId) as $q) {
            $out[] = [
                'name'     => $q['name'],
                'summary'  => $q['summary'],
                'progress' => (int) $q['progress'],
                'need'     => (int) $q['goal_count'],
                'goal'     => $q['goal_type'] . ' ' . $q['goal_target'],
            ];
        }
        return $out;
    }

    /** Local map: rooms within radius on the player's z-level. */
    private static function map(array $p, ?array $room): array
    {
        if (!$room) {
            return ['cx' => 0, 'cy' => 0, 'z' => 0, 'cells' => []];
        }
        $z = (int) $room['z'];
        $cx = (int) $room['x'];
        $cy = (int) $room['y'];
        $R = 5;
        $visited = array_flip(json_decode($p['data'] ?? '{}', true)['visited'] ?? []);

        $rows = Db::all(
            'SELECT id, vnum, x, y, name FROM mud_rooms WHERE zone_id = ? AND z = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?',
            [$room['zone_id'], $z, $cx - $R, $cx + $R, $cy - $R, $cy + $R]
        );
        $cells = [];
        foreach ($rows as $r) {
            $known = isset($visited[(int) $r['id']]) || (int) $r['id'] === (int) $room['id'];
            $dirs = [];
            if ($known) {
                foreach (World::exits((int) $r['id']) as $d => $x) {
                    if (!$x['hidden']) {
                        $dirs[] = $d;
                    }
                }
            }
            $cells[] = [
                'x'       => (int) $r['x'],
                'y'       => (int) $r['y'],
                'vnum'    => (int) $r['vnum'],
                'name'    => $known ? $r['name'] : '???',
                'visited' => $known,
                'here'    => (int) $r['id'] === (int) $room['id'],
                'exits'   => $dirs,
            ];
        }
        return ['cx' => $cx, 'cy' => $cy, 'z' => $z, 'zone' => $room['name'], 'cells' => $cells];
    }

    /**
     * Whole-world atlas for the graphical client's full-screen map.
     *
     * Returns EVERY room in the game so the client can draw the complete city
     * layout, with fog-of-war: rooms the player has never visited come back as
     * blank nodes (name null, no exits, no markers) while visited rooms carry
     * full detail. A room that sits one non-hidden step past the explored
     * frontier is tagged `ghost` so the client can hint "there's something that
     * way"; every room still carries x/y/z + zone so it can be positioned.
     *
     * @return array{here:int,visited:list<int>,zones:list<array{id:int,name:string}>,rooms:list<array>}
     */
    public static function worldMap(int $playerId): array
    {
        $p = Player::byId($playerId);
        if (!$p) {
            return ['here' => 0, 'visited' => [], 'zones' => [], 'rooms' => []];
        }

        $room     = World::room((int) $p['room_id']);
        $hereId   = $room ? (int) $room['id'] : 0;
        $hereVnum = $room ? (int) $room['vnum'] : 0;

        $data       = json_decode($p['data'] ?? '{}', true) ?: [];
        $visitedIds = [];
        foreach ($data['visited'] ?? [] as $vid) {
            $visitedIds[(int) $vid] = true;
        }
        if ($hereId) {
            $visitedIds[$hereId] = true;
        }

        // every room, indexed by id (id -> row) for exit target lookups
        $rows = Db::all('SELECT id, vnum, zone_id, name, x, y, z, flags FROM mud_rooms');
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        // every exit, grouped by origin room id
        $exitsByFrom = [];
        foreach (Db::all('SELECT from_room, to_room, dir, hidden, locked FROM mud_exits') as $x) {
            $exitsByFrom[(int) $x['from_room']][] = $x;
        }

        // rooms that carry a shop (a shop row, or the "shop" flag)
        $shopRoom = [];
        foreach (Db::all('SELECT room_id FROM mud_shops') as $sr) {
            $shopRoom[(int) $sr['room_id']] = true;
        }

        // ghost frontier: any room a visited room has a non-hidden exit into
        $ghost = [];
        foreach (array_keys($visitedIds) as $rid) {
            foreach ($exitsByFrom[$rid] ?? [] as $x) {
                if (!(int) $x['hidden']) {
                    $ghost[(int) $x['to_room']] = true;
                }
            }
        }

        $zones     = [];
        $zoneNames = [];
        foreach (Db::all('SELECT id, name FROM mud_zones ORDER BY id') as $z) {
            $zid       = (int) $z['id'];
            $zones[]   = ['id' => $zid, 'name' => $z['name']];
            $zoneNames[$zid] = (string) $z['name'];
        }

        $visitedVnums = [];
        $out          = [];
        foreach ($rows as $r) {
            $rid   = (int) $r['id'];
            $zid   = (int) $r['zone_id'];
            $known = isset($visitedIds[$rid]);
            if ($known) {
                $visitedVnums[] = (int) $r['vnum'];
            }

            $node = [
                'vnum'     => (int) $r['vnum'],
                'x'        => (int) $r['x'],
                'y'        => (int) $r['y'],
                'z'        => (int) $r['z'],
                'zone'     => $zid,
                'zoneName' => $zoneNames[$zid] ?? '',
                'name'     => $known ? $r['name'] : null,
                'exits'    => [],
            ];

            if ($known) {
                $flags = (string) $r['flags'];
                foreach ($exitsByFrom[$rid] ?? [] as $x) {
                    if ((int) $x['hidden']) {
                        continue; // server only ever ships discovered, non-hidden exits
                    }
                    $to = $byId[(int) $x['to_room']] ?? null;
                    $node['exits'][] = [
                        'dir'    => $x['dir'],
                        'to'     => $to ? (int) $to['vnum'] : 0,
                        'hidden' => false,
                        'locked' => (bool) (int) $x['locked'],
                    ];
                }
                $node['shop']  = isset($shopRoom[$rid]) || str_contains($flags, 'shop');
                $node['safe']  = str_contains($flags, 'safe');
                $node['board'] = str_contains($flags, 'board');
                $node['bank']  = str_contains($flags, 'bank');
            } elseif (isset($ghost[$rid])) {
                $node['ghost'] = true;
            }

            $out[] = $node;
        }

        return [
            'here'    => $hereVnum,
            'visited' => array_values(array_unique($visitedVnums)),
            'zones'   => $zones,
            'rooms'   => $out,
        ];
    }

    /* ---- sprite / icon keys -------------------------------------- */

    public static function itemIcon(array $tpl): string
    {
        if (!empty($tpl['icon'])) {
            return $tpl['icon'];
        }
        return Icons::forItem(
            (string) ($tpl['name'] ?? ''),
            (string) ($tpl['keywords'] ?? ''),
            (string) ($tpl['type'] ?? 'junk'),
            (string) ($tpl['slot'] ?? ''),
            (string) ($tpl['flags'] ?? '')
        );
    }

    public static function mobSprite(array $t): string
    {
        $kw = strtolower($t['keywords'] . ' ' . $t['name']);
        $f = $t['faction'];
        foreach ([
            'ratking' => 'boss', 'curator' => 'ai', 'warden' => 'ai', 'kitsune' => 'boss',
            'maxtac' => 'maxtac', 'rat' => 'rat', 'cat' => 'cat', 'jackal' => 'dog', 'dog' => 'dog',
            'drone' => 'drone', 'construct' => 'construct', 'ghost' => 'ghost', 'crawler' => 'ghoul',
            'ghoul' => 'ghoul', 'cable' => 'construct', 'pigeon' => 'civ', 'thing' => 'ghoul',
        ] as $needle => $sprite) {
            if (str_contains($kw, $needle)) {
                return $sprite;
            }
        }
        if (str_contains((string) $t['flags'], 'boss')) {
            return 'boss';
        }
        return match ($f) {
            'police' => 'cop', 'tygerclaw', 'maelstrom' => 'ganger', 'scav' => 'scav',
            'raffen' => 'raffen', 'corpo', 'arasaka', 'trauma' => 'corpo',
            'nomad' => 'nomad', 'fixer' => 'fixer', 'bazaar', 'smuggler', 'street' => 'punk',
            'ai', 'machine' => 'construct', 'wild' => 'ghoul',
            default => 'civ',
        };
    }

    public static function zoneTheme(string $zone): string
    {
        return match ($zone) {
            'kabuki', 'watson' => 'street',
            'corpo'    => 'corpo',
            'zone'     => 'ruin',
            'undercity' => 'tunnel',
            'arcade'   => 'arcade',
            'badlands' => 'desert',
            'blackwall' => 'grid',
            default    => 'street',
        };
    }

    /** Archetype cards for the character-create screen. */
    public static function archetypes(): array
    {
        $out = [];
        $i = 1;
        foreach (Player::ARCHETYPES as $slug => $a) {
            $out[] = [
                'n'     => $i++,
                'slug'  => $slug,
                'name'  => $a['name'],
                'blurb' => $a['blurb'],
                'stats' => $a['stats'],
                'hp'    => $a['hp'],
                'energy' => $a['energy'],
            ];
        }
        return $out;
    }
}
