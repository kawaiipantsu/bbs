<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * The MUD character. One row per BBS user. Handles creation, derived stats
 * (base + implants + active effects), regen, XP / levelling and death.
 */
final class Player
{
    public const STATS = ['body', 'reflex', 'intel', 'cool', 'tech'];
    public const SKILLS = ['hacking', 'netrun', 'stealth', 'melee', 'firearms', 'athletics', 'streetwise', 'engineering'];

    public const WEAR_SLOTS = ['head', 'eyes', 'face', 'neck', 'torso', 'arms', 'hands', 'back', 'waist', 'legs', 'feet'];
    public const IMPLANT_SLOTS = ['implant_neural', 'implant_ocular', 'implant_arm', 'implant_skeleton', 'implant_dermal'];

    public const ARCHETYPES = [
        'netrunner' => ['name' => 'Netrunner', 'stats' => ['body' => 4, 'reflex' => 5, 'intel' => 8, 'cool' => 5, 'tech' => 8],
                        'hp' => 26, 'energy' => 30, 'kit' => [2001, 3010, 4001, 5002, 6001],
                        'blurb' => 'You live half in the Net. ICE fears you; a baseball bat does not.'],
        'solo'      => ['name' => 'Solo', 'stats' => ['body' => 8, 'reflex' => 8, 'intel' => 4, 'cool' => 6, 'tech' => 4],
                        'hp' => 40, 'energy' => 16, 'kit' => [1004, 3001, 3020, 5001, 6001],
                        'blurb' => 'Gun for hire. You solve problems other people are too squeamish to.'],
        'techie'    => ['name' => 'Techie', 'stats' => ['body' => 5, 'reflex' => 5, 'intel' => 6, 'cool' => 5, 'tech' => 9],
                        'hp' => 30, 'energy' => 22, 'kit' => [1002, 4002, 4010, 4020, 6002],
                        'blurb' => 'You can build a turret out of a toaster. People owe you favours.'],
    ];

    /** @return array<string,mixed>|null the mud_players row (raw) */
    public static function forUser(int $userId): ?array
    {
        return Db::one('SELECT * FROM mud_players WHERE user_id = ?', [$userId]);
    }

    public static function byId(int $id): ?array
    {
        return Db::one('SELECT * FROM mud_players WHERE id = ?', [$id]);
    }

    public static function create(int $userId, string $name, string $archetype): array
    {
        $a = self::ARCHETYPES[$archetype] ?? self::ARCHETYPES['netrunner'];
        $start = (int) (World::cfg('start_room', '1'));
        $safe  = (int) (World::cfg('respawn_room', (string) $start));

        $pid = Db::insert('mud_players', [
            'user_id'         => $userId,
            'name'            => mb_substr($name, 0, 32),
            'archetype'       => $archetype,
            'level'           => 1,
            'hp'              => $a['hp'],
            'max_hp'          => $a['hp'],
            'energy'          => $a['energy'],
            'max_energy'      => $a['energy'],
            'stats'           => json_encode($a['stats']),
            'money'           => 75,
            'room_id'         => $start,
            'respawn_room_id' => $safe,
            'data'            => json_encode(['visited' => [$start], 'flags' => []]),
            'created_at'      => date('Y-m-d H:i:s'),
            'last_cmd_at'     => date('Y-m-d H:i:s'),
        ]);
        foreach (self::SKILLS as $s) {
            Db::q('INSERT IGNORE INTO mud_player_skills (player_id, skill, level) VALUES (?, ?, 1)', [$pid, $s]);
        }
        // starting kit into inventory, auto-equip wearables/weapons
        foreach ($a['kit'] as $vnum) {
            $iid = World::spawnItem($vnum, 'player', $pid);
            if ($iid) {
                $tpl = World::itemInstance($iid)['tpl'];
                if (in_array($tpl['slot'], array_merge(['wield', 'held'], self::WEAR_SLOTS, self::IMPLANT_SLOTS), true)) {
                    self::equip($pid, $iid, $tpl['slot']);
                }
            }
        }
        World::event($pid, 'create', "$name jacked in for the first time as a {$a['name']}.");
        return self::byId($pid);
    }

    /* ---- derived stats -------------------------------------------------- */

    /** Base stats + implant mods + effect mods. */
    public static function effectiveStats(array $p): array
    {
        $base = json_decode($p['stats'] ?? '{}', true) ?: [];
        $out = [];
        foreach (self::STATS as $s) {
            $out[$s] = (int) ($base[$s] ?? 4);
        }
        foreach (self::equipment($p['id']) as $eq) {
            foreach (($eq['tpl']['stat_mods'] ?? []) as $k => $v) {
                if (isset($out[$k])) {
                    $out[$k] += (int) $v;
                }
            }
        }
        foreach (self::effects((int) $p['id']) as $ef) {
            foreach (($ef['stat_mods'] ?? []) as $k => $v) {
                if (isset($out[$k])) {
                    $out[$k] += (int) $v;
                }
            }
        }
        return $out;
    }

    public static function armorClass(array $p): int
    {
        $ac = 0;
        foreach (self::equipment($p['id']) as $eq) {
            $ac += (int) ($eq['tpl']['armor'] ?? 0);
        }
        $st = self::effectiveStats($p);
        return $ac + intdiv($st['reflex'], 3);
    }

    /** [dice, bonus, verb] for the wielded weapon (or fists). */
    public static function weapon(array $p): array
    {
        $eq = self::equipmentSlot((int) $p['id'], 'wield');
        $st = self::effectiveStats($p);
        if (!$eq) {
            return ['1d4', intdiv($st['body'], 3), 'punch'];
        }
        $t = $eq['tpl'];
        $skill = in_array($t['type'], ['weapon'], true) && str_contains($t['flags'], 'ranged') ? 'firearms' : 'melee';
        $bonus = intdiv(str_contains($t['flags'], 'ranged') ? $st['reflex'] : $st['body'], 3)
               + intdiv(self::skill((int) $p['id'], $skill), 2);
        return [$t['damage_dice'] ?: '1d6', $bonus, str_contains($t['flags'], 'ranged') ? 'shoot' : 'strike'];
    }

    public static function attackRating(array $p): int
    {
        $st = self::effectiveStats($p);
        $eq = self::equipmentSlot((int) $p['id'], 'wield');
        $ranged = $eq && str_contains($eq['tpl']['flags'], 'ranged');
        return ($ranged ? $st['reflex'] : $st['body'])
             + $p['level']
             + intdiv(self::skill((int) $p['id'], $ranged ? 'firearms' : 'melee'), 2)
             + array_sum(array_map(static fn ($e) => $e['dmg_bonus'], self::effects((int) $p['id'])));
    }

    /* ---- equipment / inventory --------------------------------------- */

    /** @return list<array> instance rows (tpl merged) currently equipped */
    public static function equipment(int $playerId): array
    {
        $rows = Db::all(
            'SELECT e.slot, i.* FROM mud_player_equipment e JOIN mud_item_instances i ON i.id = e.instance_id
             WHERE e.player_id = ?',
            [$playerId]
        );
        foreach ($rows as &$r) {
            $r['tpl'] = World::itemTemplate((int) $r['template_id']);
        }
        return array_values(array_filter($rows, static fn ($r) => $r['tpl'] !== null));
    }

    public static function equipmentSlot(int $playerId, string $slot): ?array
    {
        foreach (self::equipment($playerId) as $eq) {
            if ($eq['slot'] === $slot) {
                return $eq;
            }
        }
        return null;
    }

    public static function equip(int $playerId, int $instId, string $slot): void
    {
        Db::q('DELETE FROM mud_player_equipment WHERE player_id = ? AND instance_id = ?', [$playerId, $instId]);
        Db::q(
            'INSERT INTO mud_player_equipment (player_id, slot, instance_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE instance_id = VALUES(instance_id)',
            [$playerId, $slot, $instId]
        );
        World::moveItem($instId, 'player', $playerId);
    }

    public static function unequip(int $playerId, string $slot): void
    {
        Db::q('DELETE FROM mud_player_equipment WHERE player_id = ? AND slot = ?', [$playerId, $slot]);
    }

    public static function isEquipped(int $playerId, int $instId): ?string
    {
        return Db::val('SELECT slot FROM mud_player_equipment WHERE player_id = ? AND instance_id = ?', [$playerId, $instId]);
    }

    /** carried (not equipped) items */
    public static function inventory(int $playerId): array
    {
        $eq = array_column(
            Db::all('SELECT instance_id FROM mud_player_equipment WHERE player_id = ?', [$playerId]),
            'instance_id'
        );
        $items = World::items('player', $playerId);
        return array_values(array_filter($items, static fn ($i) => !in_array($i['id'], $eq, true)));
    }

    public static function carryWeight(int $playerId): float
    {
        $w = 0.0;
        foreach (World::items('player', $playerId) as $i) {
            $w += (float) $i['tpl']['weight'];
        }
        return $w;
    }

    public static function maxCarry(array $p): float
    {
        return 30 + self::effectiveStats($p)['body'] * 4 + $p['level'] * 2;
    }

    /* ---- effects / skills ------------------------------------------ */

    public static function effects(int $playerId): array
    {
        static $cache = [];
        if (!isset($cache[$playerId])) {
            $rows = Db::all('SELECT * FROM mud_player_effects WHERE player_id = ? AND expires_at > NOW()', [$playerId]);
            foreach ($rows as &$r) {
                $r['stat_mods'] = $r['stat_mods'] ? json_decode($r['stat_mods'], true) : [];
            }
            $cache[$playerId] = $rows;
        }
        return $cache[$playerId];
    }

    public static function flushEffects(int $playerId): void
    {
        // clear the tiny static cache in effects()
    }

    public static function addEffect(int $playerId, string $name, array $mods, int $secs, int $dmgBonus = 0, string $source = ''): void
    {
        Db::q('DELETE FROM mud_player_effects WHERE player_id = ? AND name = ?', [$playerId, $name]);
        Db::insert('mud_player_effects', [
            'player_id'  => $playerId,
            'name'       => $name,
            'source'     => $source,
            'stat_mods'  => $mods ? json_encode($mods) : null,
            'dmg_bonus'  => $dmgBonus,
            'expires_at' => date('Y-m-d H:i:s', time() + $secs),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function skill(int $playerId, string $skill): int
    {
        return (int) Db::val('SELECT level FROM mud_player_skills WHERE player_id = ? AND skill = ?', [$playerId, $skill]);
    }

    public static function trainSkill(int $playerId, string $skill, int $xp): ?string
    {
        $row = Db::one('SELECT level, xp FROM mud_player_skills WHERE player_id = ? AND skill = ?', [$playerId, $skill]);
        if (!$row) {
            Db::q('INSERT INTO mud_player_skills (player_id, skill, level, xp) VALUES (?,?,1,?)', [$playerId, $skill, $xp]);
            return null;
        }
        $newXp = (int) $row['xp'] + $xp;
        $lvl = (int) $row['level'];
        $need = $lvl * 40;
        if ($newXp >= $need && $lvl < 20) {
            Db::q('UPDATE mud_player_skills SET level = level + 1, xp = ? WHERE player_id = ? AND skill = ?', [$newXp - $need, $playerId, $skill]);
            return $skill;
        }
        Db::q('UPDATE mud_player_skills SET xp = ? WHERE player_id = ? AND skill = ?', [$newXp, $playerId, $skill]);
        return null;
    }

    /* ---- xp / level / death --------------------------------------- */

    /** Total XP needed to REACH $lvl. Level 1 is the start, so it's 0.
     *  The old curve (80·lvl^1.9) made level 1 "cost" 80, which the progress
     *  bars subtracted as the level floor - so a fresh runner's bar sat at
     *  0% until 80 XP and levelling to 2 took ~50 kills. Softened for the
     *  early game. */
    public static function xpForLevel(int $lvl): int
    {
        if ($lvl <= 1) {
            return 0;
        }
        return (int) round(30 * pow($lvl - 1, 2.1));
    }

    /** @return list<string> level-up messages */
    public static function grantXp(int $playerId, int $xp): array
    {
        $p = self::byId($playerId);
        $msgs = [];
        $newXp = (int) $p['xp'] + $xp;
        $lvl = (int) $p['level'];
        $maxHp = (int) $p['max_hp'];
        $maxEn = (int) $p['max_energy'];
        $pts = (int) $p['unspent_points'];
        while ($lvl < 60 && $newXp >= self::xpForLevel($lvl + 1)) {
            $lvl++;
            $st = json_decode($p['stats'], true);
            $hpGain = 6 + intdiv((int) ($st['body'] ?? 5), 2) + random_int(0, 3);
            $enGain = 3 + intdiv((int) ($st['tech'] ?? 5), 3);
            $maxHp += $hpGain;
            $maxEn += $enGain;
            $pts += 3;
            $msgs[] = "|11*** You reach LEVEL $lvl.  +$hpGain max HP, +$enGain max heat, +3 stat points. ***";
        }
        Db::q(
            'UPDATE mud_players SET xp = ?, level = ?, max_hp = ?, max_energy = ?, unspent_points = ?,
             hp = LEAST(hp + ?, ?), energy = LEAST(energy + ?, ?) WHERE id = ?',
            [$newXp, $lvl, $maxHp, $maxEn, $pts,
             $msgs ? $maxHp : 0, $maxHp, $msgs ? $maxEn : 0, $maxEn, $playerId]
        );
        return $msgs;
    }

    /** @return list<string> */
    public static function die(int $playerId, string $cause): array
    {
        $p = self::byId($playerId);
        $fell = (int) $p['room_id'];
        $lost = (int) floor($p['money'] * 0.25);

        // everything you were carrying (not worn) stays where you fell, in a
        // body bag - go back for it before something else does.
        $carried = self::inventory($playerId);
        $bagged = 0;
        foreach ($carried as $i) {
            if (str_contains((string) $i['tpl']['flags'], 'nodrop')) {
                continue;
            }
            World::moveItem((int) $i['id'], 'room', $fell);
            $bagged++;
        }
        if ($bagged > 0) {
            World::spawnItem(6918, 'room', $fell); // a body bag marker
        }

        Db::q(
            "UPDATE mud_players SET hp = GREATEST(1, ROUND(max_hp * 0.25)), energy = 0, state = 'idle',
             pos = 'standing', target_mob = NULL, room_id = respawn_room_id, deaths = deaths + 1,
             money = money - ? WHERE id = ?",
            [$lost, $playerId]
        );
        Db::q('DELETE FROM mud_player_effects WHERE player_id = ?', [$playerId]);
        World::event($playerId, 'death', "{$p['name']} flatlined ($cause). Trauma Team billed ¥$lost.");
        $out = [
            '|12You black out. Cold. Then bright light and a Trauma Team invoice.',
            "|08You wake at a safehouse, patched up. ¥$lost gone in fees.",
        ];
        if ($bagged > 0) {
            $out[] = "|08Everything you were carrying ($bagged item" . ($bagged === 1 ? '' : 's') . ') is still where you fell. Better hurry.';
        }
        return $out;
    }

    /* ---- NCPD wanted level -------------------------------------- */

    public static function wanted(array|int $p): int
    {
        $v = is_array($p) ? ($p['wanted'] ?? 0) : (int) Db::val('SELECT wanted FROM mud_players WHERE id = ?', [$p]);
        return max(0, min(100, (int) $v));
    }

    /** @return int the new wanted level */
    public static function addWanted(int $playerId, int $delta): int
    {
        Db::q('UPDATE mud_players SET wanted = GREATEST(0, LEAST(100, wanted + ?)) WHERE id = ?', [$delta, $playerId]);
        return (int) Db::val('SELECT wanted FROM mud_players WHERE id = ?', [$playerId]);
    }

    public static function clearWanted(int $playerId): void
    {
        Db::q('UPDATE mud_players SET wanted = 0 WHERE id = ?', [$playerId]);
    }

    public static function touch(int $playerId): void
    {
        Db::q('UPDATE mud_players SET last_cmd_at = NOW(), playtime_secs = playtime_secs + LEAST(300, TIMESTAMPDIFF(SECOND, last_cmd_at, NOW())) WHERE id = ?', [$playerId]);
    }

    public static function visit(array &$p, int $roomId): void
    {
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $d['visited'] = $d['visited'] ?? [];
        if (!in_array($roomId, $d['visited'], true)) {
            $d['visited'][] = $roomId;
            if (count($d['visited']) > 4000) {
                array_shift($d['visited']);
            }
            Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $p['id']]);
            $p['data'] = json_encode($d);
        }
    }

    public static function flag(array $p, string $key): bool
    {
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        return !empty($d['flags'][$key]);
    }

    public static function setFlag(int $playerId, string $key, $val = true): void
    {
        $p = self::byId($playerId);
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $d['flags'][$key] = $val;
        Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $playerId]);
    }
}
