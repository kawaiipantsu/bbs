<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * Lightweight quest tracking. Quests are rows in mud_quests with a single goal
 * (kill / collect / visit / hack / talk). Progress ticks from game events.
 */
final class Quests
{
    public static function forGiver(int $mobVnum): array
    {
        return Db::all('SELECT * FROM mud_quests WHERE giver_vnum = ? ORDER BY level_req, vnum', [$mobVnum]);
    }

    public static function active(int $playerId): array
    {
        return Db::all(
            "SELECT q.*, pq.status, pq.progress FROM mud_player_quests pq
             JOIN mud_quests q ON q.id = pq.quest_id
             WHERE pq.player_id = ? AND pq.status = 'active' ORDER BY q.vnum",
            [$playerId]
        );
    }

    public static function status(int $playerId, int $questId): ?array
    {
        return Db::one('SELECT * FROM mud_player_quests WHERE player_id = ? AND quest_id = ?', [$playerId, $questId]);
    }

    /** @return list<string> */
    public static function accept(int $playerId, array $quest): array
    {
        $p = Player::byId($playerId);
        if ((int) $p['level'] < (int) $quest['level_req']) {
            return ['|08You are not ready for that job yet.'];
        }
        $s = self::status($playerId, (int) $quest['id']);
        if ($s && $s['status'] === 'done') {
            return ['|08You already ran that job.'];
        }
        if ($s && $s['status'] === 'active') {
            return ['|08You are already on that one. Type |15quests|08 to check it.'];
        }
        Db::q(
            'INSERT INTO mud_player_quests (player_id, quest_id, status, progress) VALUES (?,?,"active",0)
             ON DUPLICATE KEY UPDATE status = "active", progress = 0',
            [$playerId, $quest['id']]
        );
        return [
            "|11JOB ACCEPTED: {$quest['name']}",
            '|07' . $quest['description'],
            "|08Goal: {$quest['goal_type']} {$quest['goal_target']} x{$quest['goal_count']}",
        ];
    }

    /** Called on kill/collect/visit/hack/talk. */
    public static function progress(int $playerId, string $type, string $target): array
    {
        $out = [];
        $rows = Db::all(
            "SELECT q.*, pq.progress FROM mud_player_quests pq JOIN mud_quests q ON q.id = pq.quest_id
             WHERE pq.player_id = ? AND pq.status = 'active' AND q.goal_type = ?",
            [$playerId, $type]
        );
        foreach ($rows as $q) {
            $match = strcasecmp($q['goal_target'], $target) === 0
                  || ($q['goal_target'] === '' )
                  || str_contains(strtolower($target), strtolower($q['goal_target']));
            if (!$match) {
                continue;
            }
            $prog = (int) $q['progress'] + 1;
            if ($prog >= (int) $q['goal_count']) {
                Db::q("UPDATE mud_player_quests SET status='done', progress=? WHERE player_id=? AND quest_id=?",
                    [$prog, $playerId, $q['id']]);
                $out[] = "|11*** JOB COMPLETE: {$q['name']} - return to your fixer for payment. ***";
            } else {
                Db::q('UPDATE mud_player_quests SET progress=? WHERE player_id=? AND quest_id=?', [$prog, $playerId, $q['id']]);
                $out[] = "|08[{$q['name']}: $prog/{$q['goal_count']}]";
            }
        }
        return $out;
    }

    /** Turn in any 'done' quests this giver owns. @return list<string> */
    public static function turnIn(int $playerId, int $giverVnum): array
    {
        $out = [];
        $rows = Db::all(
            "SELECT q.* FROM mud_player_quests pq JOIN mud_quests q ON q.id = pq.quest_id
             WHERE pq.player_id = ? AND pq.status = 'done' AND q.giver_vnum = ?",
            [$playerId, $giverVnum]
        );
        foreach ($rows as $q) {
            Db::q("UPDATE mud_player_quests SET status='rewarded' WHERE player_id=? AND quest_id=?", [$playerId, $q['id']]);
            $money = (int) $q['reward_money'];
            if ($money) {
                Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$money, $playerId]);
            }
            if ($q['reward_vnum']) {
                World::spawnItem((int) $q['reward_vnum'], 'player', $playerId);
            }
            $out[] = "|11PAID: {$q['name']} - ¥$money" . ($q['reward_vnum'] ? ' + gear' : '');
            $out = array_merge($out, Player::grantXp($playerId, (int) $q['reward_xp']));
            if ($q['next_vnum']) {
                $nx = Db::one('SELECT * FROM mud_quests WHERE vnum = ?', [(int) $q['next_vnum']]);
                if ($nx) {
                    $lvl = (int) Db::val('SELECT level FROM mud_players WHERE id = ?', [$playerId]);
                    $out[] = '';
                    if ($lvl >= (int) $nx['level_req']) {
                        $out = array_merge($out, self::accept($playerId, $nx));
                    } else {
                        $out[] = "|08There's more where that came from: |11{$nx['name']}|08 (needs level {$nx['level_req']}). Come back.";
                    }
                }
            }
        }
        return $out;
    }
}
