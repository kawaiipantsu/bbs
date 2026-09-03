<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * Users Directory (slug `userdir`) - a classic BBS "yellow pages" / user list.
 *
 * Paginated table of active members: handle, tagline, location, join month,
 * last-on (relative), and calls / posts counts. Sort cycles with S between
 * newest, most posts, most calls and A-Z. Sort + page persist in $st.
 */
final class UserDirModule extends Module
{
    private const PER_PAGE = 14;

    /** @var list<array{sql:string,label:string}> */
    private const SORTS = [
        ['sql' => 'u.created_at DESC, u.id DESC',        'label' => 'newest members'],
        ['sql' => 'u.posts DESC, u.id ASC',              'label' => 'most posts'],
        ['sql' => 'u.calls DESC, u.id ASC',              'label' => 'most calls'],
        ['sql' => 'LOWER(u.handle) ASC, u.id ASC',       'label' => 'A-Z by handle'],
    ];

    public static function slugs(): array
    {
        return ['userdir'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));

        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }

        // ---- persisted sub-state -------------------------------------
        $sort = (int) ($st['sort'] ?? 0);
        if ($sort < 0 || $sort >= count(self::SORTS)) {
            $sort = 0;
        }
        $page = max(0, (int) ($st['page'] ?? 0));

        // ---- counts / paging ---------------------------------------------
        $total = (int) Db::val(
            "SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL"
        );
        $active7 = (int) Db::val(
            "SELECT COUNT(*) FROM users
             WHERE status = 'active' AND deleted_at IS NULL
               AND last_login_at IS NOT NULL
               AND last_login_at > NOW() - INTERVAL 7 DAY"
        );
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        // ---- key handling ----------------------------------------------
        if ($key === 'S') {
            $sort = ($sort + 1) % count(self::SORTS);
            $page = 0;
        } elseif ($key === 'N' || $key === 'PAGEDOWN' || $key === 'DOWN'
                 || $key === "\r" || $key === ' ' || $key === 'RIGHT') {
            $page++;
        } elseif ($key === 'P' || $key === 'PAGEUP' || $key === 'UP' || $key === 'LEFT') {
            $page--;
        }
        $page = max(0, min($pages - 1, $page));

        $st['sort'] = $sort;
        $st['page'] = $page;

        // ---- fetch the page ------------------------------------------------
        $rows = $total > 0 ? Db::all(
            "SELECT u.handle, u.tagline, u.location, u.created_at, u.last_login_at,
                    u.calls, u.posts
             FROM users u
             WHERE u.status = 'active' AND u.deleted_at IS NULL
             ORDER BY " . self::SORTS[$sort]['sql'] . "
             LIMIT ? OFFSET ?",
            [self::PER_PAGE, $page * self::PER_PAGE]
        ) : [];

        // ---- render ------------------------------------------------------
        $right = 'page ' . ($page + 1) . '/' . $pages . '  ·  ' . number_format($total) . ' runners';
        $f = Frame::make('screen')->title('Users Directory')->mode('menu')
            ->header('Users Directory', $right)->blank();

        $f->pipe(sprintf(
            '|07   %s|08 members on file   ·   |10%s|08 dialed in over the last 7 days   ·   sort: |11%s',
            number_format($total),
            number_format($active7),
            self::SORTS[$sort]['label']
        ));
        $f->blank();

        // column header + separator
        $f->pipe('|15   ' . str_pad('HANDLE', 16) . str_pad('TAGLINE', 34)
            . str_pad('LOCATION', 16) . str_pad('JOINED', 9)
            . str_pad('LAST ON', 11) . str_pad('CALLS', 7) . 'POSTS');
        $f->rule();

        if (!$rows) {
            $f->blank();
            $f->pipe('|08   Nobody on file yet. The lines are quiet.');
            return $f->footer('S sort · Q back');
        }

        foreach ($rows as $i => $r) {
            $alt = ($i % 2 === 0) ? '|07' : '|08';
            $f->pipe(
                '   |11' . str_pad($this->clip((string) $r['handle'], 15), 16)
                . $alt . str_pad($this->clip(trim((string) ($r['tagline'] ?? '')) ?: '—', 33), 34)
                . str_pad($this->clip(trim((string) ($r['location'] ?? '')) ?: '—', 15), 16)
                . str_pad(date('M Y', strtotime((string) $r['created_at'])), 9)
                . '|08' . str_pad($this->relative($r['last_login_at']), 11)
                . '|10' . str_pad(number_format((int) $r['calls']), 7)
                . '|14' . number_format((int) $r['posts'])
            );
        }

        $f->blank();
        $f->pipe('|08   Showing ' . (($page * self::PER_PAGE) + 1) . '-'
            . (($page * self::PER_PAGE) + count($rows)) . ' of ' . number_format($total) . '.');

        return $f->footer('S sort (' . self::SORTS[$sort]['label'] . ') · N/P or SPACE page · Q back');
    }

    /** Truncate with an ellipsis so the field never overruns its column. */
    private function clip(string $s, int $n): string
    {
        $s = trim($s);
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }

    /** last_login_at -> "just now" / "3d ago" / "2026-08-01" / "never". */
    private function relative(?string $ts): string
    {
        if ($ts === null || $ts === '' || $ts === '0000-00-00 00:00:00') {
            return 'never';
        }
        $t = strtotime($ts);
        if ($t === false) {
            return 'never';
        }
        $d = time() - $t;
        if ($d < 0) {
            $d = 0;
        }
        if ($d < 90) {
            return 'just now';
        }
        if ($d < 3600) {
            return intdiv($d, 60) . 'm ago';
        }
        if ($d < 86400) {
            return intdiv($d, 3600) . 'h ago';
        }
        if ($d < 86400 * 7) {
            return intdiv($d, 86400) . 'd ago';
        }
        if ($d < 86400 * 30) {
            return intdiv($d, 86400 * 7) . 'w ago';
        }
        return date('Y-m-d', $t);
    }
}
