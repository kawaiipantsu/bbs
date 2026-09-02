<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Context;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * Board statistics: totals, top posters, busiest boards, call graph.
 */
final class StatsModule extends Module
{
    public static function slugs(): array
    {
        return ['stats'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }

        $s = Context::stats();
        $f = Frame::make('screen')->title('Statistics')->mode('menu')->header('Board Statistics')->blank();

        $f->pipe('|14   TOTALS')
          ->pipe(sprintf('|07     Users %s|08   Calls %s|08   Messages %s|08   Files %s|08   One-liners %s',
              $this->n($s['users_total']), $this->n($s['calls_total']), $this->n($s['messages_total']),
              $this->n($s['files_total']), $this->n($s['oneliners_total'])))
          ->blank();

        // top posters
        $top = Db::all(
            "SELECT from_handle AS h, COUNT(*) c FROM messages WHERE deleted_at IS NULL AND from_handle <> ''
             GROUP BY from_handle ORDER BY c DESC LIMIT 8"
        );
        $f->pipe('|14   TOP POSTERS');
        foreach ($top as $i => $r) {
            $f->pipe(sprintf('|07     %2d. %-20s |08%s', $i + 1, $r['h'], $this->bar((int) $r['c'], (int) ($top[0]['c'] ?? 1))));
        }
        if (!$top) {
            $f->pipe('|08     no posts yet');
        }
        $f->blank();

        // busiest boards
        $bb = Db::all(
            "SELECT b.name, b.post_count FROM boards b ORDER BY b.post_count DESC LIMIT 6"
        );
        $f->pipe('|14   BUSIEST BOARDS');
        foreach ($bb as $r) {
            $f->pipe(sprintf('|07     %-24s |08%s', $r['name'], $this->bar((int) $r['post_count'], (int) ($bb[0]['post_count'] ?? 1))));
        }
        $f->blank();

        // calls last 7 days
        $calls = Db::all(
            "SELECT DATE(connected_at) d, COUNT(*) c FROM call_log
             WHERE connected_at > NOW() - INTERVAL 7 DAY GROUP BY DATE(connected_at) ORDER BY d"
        );
        $peak = max(1, ...array_map(static fn ($r) => (int) $r['c'], $calls ?: [['c' => 1]]));
        $f->pipe('|14   CALLS - LAST 7 DAYS');
        foreach ($calls as $r) {
            $f->pipe(sprintf('|07     %s  |10%s |08%d', $r['d'], str_repeat('█', (int) round(40 * $r['c'] / $peak)), $r['c']));
        }
        if (!$calls) {
            $f->pipe('|08     quiet week');
        }

        return $f->footer('Q back');
    }

    private function n(int|string $v): string
    {
        return number_format((int) $v);
    }

    private function bar(int $v, int $max): string
    {
        $w = (int) round(36 * $v / max(1, $max));
        return str_repeat('▓', $w) . ' ' . $v;
    }
}
