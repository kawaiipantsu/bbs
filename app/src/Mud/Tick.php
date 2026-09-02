<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * The world heartbeat: respawns, mob wandering, regen, hunger, expiring buffs
 * and aggro. Runs from contrib/mud-tick.php on a schedule AND lazily at the top
 * of every player command (so it works with or without cron).
 */
final class Tick
{
    private const INTERVAL = 18;   // seconds between ticks

    public static function maybeRun(): void
    {
        $last = (int) World::cfg('last_tick', '0');
        if (time() - $last < self::INTERVAL) {
            return;
        }
        // crude lock so two concurrent requests don't both tick
        $lock = (int) World::cfg('tick_lock', '0');
        if (time() - $lock < 8) {
            return;
        }
        World::setCfg('tick_lock', (string) time());
        self::run();
        World::setCfg('last_tick', (string) time());
        World::setCfg('tick_lock', '0');
    }

    public static function run(): void
    {
        Db::q('DELETE FROM mud_player_effects WHERE expires_at < NOW()');

        // ---- respawn dead mobs ----
        $dead = Db::all(
            "SELECT mi.*, mt.respawn_secs, mt.max_hp FROM mud_mob_instances mi
             JOIN mud_mob_templates mt ON mt.id = mi.template_id
             WHERE mi.state = 'dead' AND mi.died_at < NOW() - INTERVAL mt.respawn_secs SECOND"
        );
        foreach ($dead as $mi) {
            Db::q(
                "UPDATE mud_mob_instances SET state='idle', hp=?, room_id=spawn_room_id, died_at=NULL, target_player=NULL, last_act_at=NOW() WHERE id=?",
                [(int) $mi['max_hp'], $mi['id']]
            );
        }

        // ---- wander ----
        $wanderers = Db::all(
            "SELECT mi.id, mi.room_id, mt.behavior, mt.faction, r.zone_id
             FROM mud_mob_instances mi
             JOIN mud_mob_templates mt ON mt.id = mi.template_id
             JOIN mud_rooms r ON r.id = mi.room_id
             WHERE mi.state IN ('idle','wander') AND mt.behavior LIKE '%wander%'
               AND mi.last_act_at < NOW() - INTERVAL 25 SECOND
             ORDER BY RAND() LIMIT 40"
        );
        foreach ($wanderers as $w) {
            if (random_int(1, 3) !== 1) {
                continue;
            }
            $exits = array_values(World::exits((int) $w['room_id']));
            $exits = array_filter($exits, function ($x) use ($w) {
                $dest = World::room((int) $x['to_room']);
                return $dest && (int) $dest['zone_id'] === (int) $w['zone_id']
                    && !str_contains((string) $dest['flags'], 'shop')
                    && !$x['locked'] && !$x['hidden'];
            });
            if (!$exits) {
                continue;
            }
            $x = $exits[array_rand($exits)];
            Db::q("UPDATE mud_mob_instances SET room_id=?, last_act_at=NOW() WHERE id=?", [$x['to_room'], $w['id']]);
        }

        // ---- mob HP regen (not fighting) ----
        Db::q(
            "UPDATE mud_mob_instances mi JOIN mud_mob_templates mt ON mt.id = mi.template_id
             SET mi.hp = LEAST(mi.hp + GREATEST(1, ROUND(mt.max_hp*0.08)), mt.max_hp)
             WHERE mi.state NOT IN ('dead','fighting') AND mi.hp < mt.max_hp"
        );

        // ---- players: regen, hunger/thirst ----
        $players = Db::all("SELECT * FROM mud_players WHERE last_cmd_at > NOW() - INTERVAL 20 MINUTE");
        foreach ($players as $p) {
            if ($p['state'] === 'fighting') {
                continue;
            }
            $mult = match ($p['pos']) { 'sleeping' => 6, 'resting' => 3, 'sitting' => 2, default => 1 };
            $fed = ((int) $p['hunger'] > 0 && (int) $p['thirst'] > 0) ? 1.0 : 0.4;
            $hpRegen = (int) ceil(($p['max_hp'] * 0.04 + 1) * $mult * $fed);
            $enRegen = (int) ceil(($p['max_energy'] * 0.06 + 1) * $mult * $fed);
            Db::q(
                'UPDATE mud_players SET hp = LEAST(hp + ?, max_hp), energy = LEAST(energy + ?, max_energy),
                 hunger = GREATEST(0, hunger - 1), thirst = GREATEST(0, thirst - 1) WHERE id = ?',
                [$hpRegen, $enRegen, $p['id']]
            );
        }

        // ---- aggro: aggressive idle mobs jump players sharing their room ----
        $threats = Db::all(
            "SELECT mi.id AS mob_id, mi.room_id, p.id AS player_id
             FROM mud_mob_instances mi
             JOIN mud_mob_templates mt ON mt.id = mi.template_id
             JOIN mud_players p ON p.room_id = mi.room_id
             JOIN mud_rooms r ON r.id = mi.room_id
             WHERE mi.state = 'idle' AND mt.behavior LIKE '%aggressive%'
               AND p.state <> 'dead' AND p.last_cmd_at > NOW() - INTERVAL 4 MINUTE
               AND r.flags NOT LIKE '%safe%'
             ORDER BY RAND() LIMIT 20"
        );
        foreach ($threats as $t) {
            $lines = Combat::mobStrike((int) $t['mob_id'], (int) $t['player_id']);
            if ($lines) {
                self::queue((int) $t['player_id'], $lines);
            }
        }
    }

    /** Stash lines to show the player on their next command. */
    public static function queue(int $playerId, array $lines): void
    {
        $p = Player::byId($playerId);
        if (!$p) {
            return;
        }
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $d['pending'] = array_slice(array_merge($d['pending'] ?? [], $lines), -40);
        Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $playerId]);
    }

    /** @return list<string> pending lines, cleared. */
    public static function drain(array &$p): array
    {
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $lines = $d['pending'] ?? [];
        if ($lines) {
            unset($d['pending']);
            Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $p['id']]);
            $p['data'] = json_encode($d);
        }
        return $lines;
    }
}
