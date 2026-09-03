<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Core\Db;

/**
 * Badge system. The `achievements` table is a catalogue with a small JSON
 * `rule`; `sync()` recomputes a user's derived stats and grants any rule that
 * is now satisfied. That keeps the whole thing to one hook point (a throttled
 * call from Engine::dispatch) instead of sprinkling grant() through the code.
 */
final class Achievements
{
    /** Grant one badge if not already held. Returns true when newly granted. */
    public static function grant(int $userId, string $code): bool
    {
        if ($userId <= 0 || $code === '') {
            return false;
        }
        try {
            $n = Db::q(
                'INSERT IGNORE INTO user_achievements (user_id, code, earned_at) VALUES (?, ?, ?)',
                [$userId, $code, date('Y-m-d H:i:s')]
            )->rowCount();
            return $n > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function has(int $userId, string $code): bool
    {
        return (bool) Db::val(
            'SELECT 1 FROM user_achievements WHERE user_id = ? AND code = ?',
            [$userId, $code]
        );
    }

    /** @return array{earned:int,total:int,points:int,earned_points:int} */
    public static function summary(int $userId): array
    {
        $total  = (int) Db::val('SELECT COUNT(*) FROM achievements WHERE enabled = 1');
        $tPts   = (int) Db::val('SELECT COALESCE(SUM(points),0) FROM achievements WHERE enabled = 1');
        $earned = (int) Db::val('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$userId]);
        $ePts   = (int) Db::val(
            'SELECT COALESCE(SUM(a.points),0) FROM user_achievements ua
             JOIN achievements a ON a.code = ua.code WHERE ua.user_id = ? AND a.enabled = 1',
            [$userId]
        );
        return ['earned' => $earned, 'total' => $total, 'points' => $tPts, 'earned_points' => $ePts];
    }

    /**
     * Full catalogue for a user, each row + earned_at (null if locked).
     * @return list<array<string,mixed>>
     */
    public static function forUser(int $userId): array
    {
        return Db::all(
            'SELECT a.*, ua.earned_at
               FROM achievements a
               LEFT JOIN user_achievements ua ON ua.code = a.code AND ua.user_id = ?
              WHERE a.enabled = 1
              ORDER BY a.sort, a.code',
            [$userId]
        );
    }

    /**
     * Recompute the derived counters used by the rules.
     * @return array<string,int>
     */
    public static function stats(int $userId): array
    {
        $u = Db::one(
            'SELECT calls, posts, uploads, downloads,
                    TIMESTAMPDIFF(DAY, created_at, NOW()) AS age_days,
                    HOUR(last_login_at) AS login_hour
               FROM users WHERE id = ?',
            [$userId]
        ) ?: [];

        $one    = (int) Db::val('SELECT COUNT(*) FROM oneliners WHERE user_id = ? AND deleted_at IS NULL', [$userId]);
        $votes  = (int) Db::val('SELECT COUNT(*) FROM poll_votes WHERE user_id = ?', [$userId]);
        $tix    = (int) Db::val('SELECT COUNT(*) FROM sysop_tickets WHERE user_id = ?', [$userId]);
        $gPlays = (int) Db::val('SELECT COUNT(*) FROM game_scores WHERE user_id = ?', [$userId]);
        $gVar   = (int) Db::val('SELECT COUNT(DISTINCT game_id) FROM game_scores WHERE user_id = ?', [$userId]);
        $triv   = (int) Db::val(
            "SELECT COALESCE(MAX(gs.score),0) FROM game_scores gs
             JOIN games g ON g.id = gs.game_id
             WHERE gs.user_id = ? AND g.module = 'trivia'",
            [$userId]
        );
        $mud = Db::one('SELECT level, kills FROM mud_players WHERE user_id = ?', [$userId]) ?: [];

        return [
            'calls'          => (int) ($u['calls'] ?? 0),
            'posts'          => (int) ($u['posts'] ?? 0),
            'uploads'        => (int) ($u['uploads'] ?? 0),
            'downloads'      => (int) ($u['downloads'] ?? 0),
            'age_days'       => (int) ($u['age_days'] ?? 0),
            'login_hour'     => $u['login_hour'] === null ? -1 : (int) $u['login_hour'],
            'oneliners'      => $one,
            'poll_votes'     => $votes,
            'tickets'        => $tix,
            'games_plays'    => $gPlays,
            'games_variety'  => $gVar,
            'trivia_best'    => $triv,
            'mud_level'      => (int) ($mud['level'] ?? 0),
            'mud_kills'      => (int) ($mud['kills'] ?? 0),
        ];
    }

    /**
     * Evaluate every rule against the user's current stats and grant the ones
     * now satisfied. Returns the list of newly granted [code => name].
     * @return array<string,string>
     */
    public static function sync(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $held = array_column(
                Db::all('SELECT code FROM user_achievements WHERE user_id = ?', [$userId]),
                'code'
            );
            $held = array_flip($held);
            $cat = Db::all('SELECT code, name, rule FROM achievements WHERE enabled = 1');
            if (!$cat) {
                return [];
            }
            $s = self::stats($userId);
            $new = [];
            foreach ($cat as $a) {
                if (isset($held[$a['code']])) {
                    continue;
                }
                if (self::ruleMet((string) $a['rule'], $s) && self::grant($userId, (string) $a['code'])) {
                    $new[(string) $a['code']] = (string) $a['name'];
                }
            }
            return $new;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string,int> $s */
    private static function ruleMet(string $ruleJson, array $s): bool
    {
        $r = json_decode($ruleJson, true);
        if (!is_array($r)) {
            return false;
        }
        return match ($r['t'] ?? '') {
            'stat' => isset($r['k'], $r['n']) && ($s[$r['k']] ?? 0) >= (int) $r['n'],
            'age'  => isset($r['n']) && ($s['age_days'] ?? 0) >= (int) $r['n'],
            'hour' => ($s['login_hour'] ?? -1) >= (int) ($r['from'] ?? 0)
                   && ($s['login_hour'] ?? -1) <= (int) ($r['to'] ?? 0),
            default => false,
        };
    }
}
