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
        ];
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
            'shop'    => $shop ? ['name' => $shop['name'], 'greeting' => $shop['greeting']] : null,
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
                    'id'   => (int) $i['id'],
                    'name' => $i['tpl']['name'],
                    'icon' => self::itemIcon($i['tpl']),
                    'type' => $i['tpl']['type'],
                    'slot' => $i['tpl']['slot'] ?: null,
                    'kw'   => explode(' ', $i['tpl']['keywords'])[0],
                    'qty'  => 0,
                    'value' => (int) $i['tpl']['value'],
                    'illegal' => str_contains((string) $i['tpl']['flags'], 'illegal'),
                ];
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
                'name' => $eq['tpl']['name'],
                'icon' => self::itemIcon($eq['tpl']),
                'kw'   => explode(' ', $eq['tpl']['keywords'])[0],
            ];
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

    /* ---- sprite / icon keys -------------------------------------- */

    public static function itemIcon(array $tpl): string
    {
        $t = $tpl['type'];
        $map = [
            'weapon' => str_contains($tpl['flags'], 'ranged') ? 'gun' : 'blade',
            'armor'  => str_starts_with((string) $tpl['slot'], 'implant_') ? 'chip' : 'armor',
            'implant' => 'chip', 'computer' => 'deck', 'food' => 'food', 'drink' => 'drink',
            'drug' => 'stim', 'gadget' => 'gadget', 'light' => 'light', 'container' => 'bag',
            'currency' => 'eddies', 'lore' => 'shard', 'material' => 'scrap', 'junk' => 'junk',
        ];
        return $map[$t] ?? 'junk';
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
