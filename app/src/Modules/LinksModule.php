<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * Links Directory - curated, categorised outbound links (search engines, AI,
 * OSINT, red/blue team, programming, retro scene, ...). Logged-in users can
 * suggest a link; rank >= 10 is auto-approved, everyone else is held for a SysOp.
 */
final class LinksModule extends Module
{
    public static function slugs(): array
    {
        return ['links'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        // ---- add-a-link form -------------------------------------------------
        if (($st['view'] ?? '') === 'add') {
            if ($cmd === 'cancel' || $key === "\x1B") {
                $st['view'] = '';
                return $this->render($e, $st);
            }
            if ($cmd === 'submit') {
                return $this->addLink($e, $in, $st);
            }
            $cats = Db::all('SELECT id, name FROM link_categories ORDER BY sort, id');
            $opts = [];
            foreach ($cats as $c) {
                $opts[$c['id']] = $c['name'];
            }
            return Frame::make('form')->title('Suggest a Link')->header('Links · suggest a link')->blank()
                ->pipe('|07   Share something worth bookmarking. Staff review new entries.')
                ->form([
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'max' => 118],
                    ['name' => 'url', 'label' => 'URL (https://...)', 'type' => 'text', 'max' => 590],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => $opts],
                    ['name' => 'description', 'label' => 'One-line description', 'type' => 'text', 'max' => 290],
                ], 'ENTER submits · ESC cancels');
        }

        // ---- inside a category: pick a link --------------------------------
        if (($st['cat'] ?? 0) > 0) {
            $links = Db::all(
                'SELECT * FROM links WHERE category_id = ? AND deleted_at IS NULL AND is_approved = 1
                 ORDER BY sort, id',
                [(int) $st['cat']]
            );
            if ($key === "\x1B" || $key === 'Q' || $key === 'X') {
                $st['cat'] = 0;
                return $this->render($e, $st);
            }
            if ($key === 'S' && !$e->guest()) {
                $st['view'] = 'add';
                return $this->run($e, $slug, ['cmd' => 'render'], $st);
            }
            $idx = $this->pick($key);
            if ($idx !== null && isset($links[$idx])) {
                Db::q('UPDATE links SET clicks = clicks + 1 WHERE id = ?', [$links[$idx]['id']]);
                AuditLog::record('link.open', 'link', (int) $links[$idx]['id'], $links[$idx]['title']);
                return Frame::make('redirect')->mode('redirect')
                    ->meta(['url' => $links[$idx]['url'], 'newtab' => true])->title('Opening link');
            }
            return $this->renderCategory($e, (int) $st['cat'], $links);
        }

        // ---- category list -------------------------------------------------
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($key === 'S' && !$e->guest()) {
            $st['view'] = 'add';
            return $this->run($e, $slug, ['cmd' => 'render'], $st);
        }
        $cats = Db::all(
            'SELECT c.*, (SELECT COUNT(*) FROM links l WHERE l.category_id = c.id AND l.deleted_at IS NULL AND l.is_approved = 1) AS n
             FROM link_categories c ORDER BY c.sort, c.id'
        );
        $idx = $this->pick($key);
        if ($idx !== null && isset($cats[$idx])) {
            $st['cat'] = (int) $cats[$idx]['id'];
            return $this->render($e, $st);
        }
        return $this->renderCats($e, $cats);
    }

    // -----------------------------------------------------------------
    private function render(Engine $e, array &$st): Frame
    {
        if (($st['cat'] ?? 0) > 0) {
            $links = Db::all(
                'SELECT * FROM links WHERE category_id = ? AND deleted_at IS NULL AND is_approved = 1 ORDER BY sort, id',
                [(int) $st['cat']]
            );
            return $this->renderCategory($e, (int) $st['cat'], $links);
        }
        $cats = Db::all(
            'SELECT c.*, (SELECT COUNT(*) FROM links l WHERE l.category_id = c.id AND l.deleted_at IS NULL AND l.is_approved = 1) AS n
             FROM link_categories c ORDER BY c.sort, c.id'
        );
        return $this->renderCats($e, $cats);
    }

    private function renderCats(Engine $e, array $cats): Frame
    {
        $total = array_sum(array_map(static fn ($c) => (int) $c['n'], $cats));
        $f = Frame::make('screen')->title('Links Directory')->mode('menu')
            ->header('Links Directory', $total . ' links in ' . count($cats) . ' categories')->blank();
        foreach ($cats as $i => $c) {
            $f->pipe(sprintf(
                '|08 [|15%2s|08] |09%s |14%-24s |08%-3d |07%s',
                self::label($i),
                $c['icon'] !== '' ? $c['icon'] : ' ',
                mb_substr($c['name'], 0, 24),
                (int) $c['n'],
                mb_substr($c['description'], 0, 52)
            ));
        }
        $hint = $e->guest() ? 'number to open · Q back' : 'number to open · S suggest a link · Q back';
        return $f->footer($hint);
    }

    private function renderCategory(Engine $e, int $catId, array $links): Frame
    {
        $cat = Db::one('SELECT * FROM link_categories WHERE id = ?', [$catId]);
        $f = Frame::make('screen')->title('Links · ' . ($cat['name'] ?? ''))->mode('menu')
            ->header('Links · ' . ($cat['name'] ?? ''), count($links) . ' links')->blank();
        if ($cat && $cat['description']) {
            $f->pipe('|08   ' . $cat['description'])->blank();
        }
        foreach ($links as $i => $l) {
            $f->pipe(sprintf('|08 [|15%2s|08] |14%-26s |07%s', self::label($i), mb_substr($l['title'], 0, 26), mb_substr($l['description'], 0, 60)));
            $f->pipe(sprintf('      |08%s', mb_substr(preg_replace('#^https?://#', '', $l['url']) ?? $l['url'], 0, 88)));
        }
        if (!$links) {
            $f->pipe('|08   Nothing here yet.');
        }
        $hint = $e->guest() ? 'number opens in a new tab · X categories · Q back'
            : 'number opens in a new tab · S suggest · X categories · Q back';
        return $f->footer($hint);
    }

    private function addLink(Engine $e, array $in, array &$st): Frame
    {
        $d = (array) ($in['data'] ?? []);
        $title = trim((string) ($d['title'] ?? ''));
        $url = trim((string) ($d['url'] ?? ''));
        $cat = (int) ($d['category'] ?? 0);
        $desc = trim((string) ($d['description'] ?? ''));

        $err = null;
        if ($title === '' || $url === '') {
            $err = 'Title and URL are both required.';
        } elseif (!preg_match('#^https?://[^\s]+\.[^\s]+#i', $url)) {
            $err = 'That does not look like a URL.';
        } elseif (!Db::val('SELECT 1 FROM link_categories WHERE id = ?', [$cat])) {
            $err = 'Pick a category.';
        }
        if ($err) {
            $cats = Db::all('SELECT id, name FROM link_categories ORDER BY sort, id');
            $opts = [];
            foreach ($cats as $c) {
                $opts[$c['id']] = $c['name'];
            }
            return Frame::make('form')->title('Suggest a Link')->header('Links · suggest a link')->blank()
                ->pipe('|12   ' . $err)
                ->form([
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'max' => 118, 'value' => $title],
                    ['name' => 'url', 'label' => 'URL (https://...)', 'type' => 'text', 'max' => 590, 'value' => $url],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => $opts],
                    ['name' => 'description', 'label' => 'One-line description', 'type' => 'text', 'max' => 290, 'value' => $desc],
                ], 'ENTER submits · ESC cancels')->sound('error');
        }

        $approved = $e->rank() >= 10 ? 1 : 0;
        $id = Db::insert('links', [
            'category_id'  => $cat,
            'title'        => mb_substr($title, 0, 118),
            'url'          => mb_substr($url, 0, 590),
            'description'  => mb_substr($desc, 0, 290),
            'added_by'     => $e->session->userId,
            'added_handle' => $e->session->handle(),
            'is_approved'  => $approved,
            'sort'         => 999,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('link.suggest', 'link', $id, $title . ($approved ? '' : ' (pending)'));
        $st['view'] = '';
        $st['cat'] = $approved ? $cat : 0;
        return $this->render($e, $st)->sound('beep')
            ->pipe($approved ? '|10   Added to the directory. Thanks.' : '|11   Submitted - a SysOp will review it.');
    }

    private function pick(string $key): ?int
    {
        if ($key === '' || $key === 'S' || $key === 'X' || $key === 'Q') {
            return null;
        }
        if (ctype_digit($key) && $key !== '0') {
            return (int) $key - 1;
        }
        if (strlen($key) === 1 && ctype_alpha($key)) {
            return 9 + (ord($key) - 65);
        }
        return null;
    }

    private static function label(int $i): string
    {
        return $i < 9 ? (string) ($i + 1) : chr(65 + $i - 9);
    }
}
