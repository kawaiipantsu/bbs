<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Auth\Rbac;
use Bbs\Auth\Session;
use Bbs\Core\Db;

/**
 * Data-driven menu system. Menus and their items live in the `menus` /
 * `menu_items` tables and are fully editable in the SysOp area.
 */
final class Menu
{
    /** @return array<string,mixed>|null */
    public static function load(string $slug): ?array
    {
        return Db::one('SELECT * FROM menus WHERE slug = ?', [$slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function items(string $slug): array
    {
        return Db::all(
            'SELECT mi.* FROM menu_items mi
             JOIN menus m ON m.id = mi.menu_id
             WHERE m.slug = ? AND mi.enabled = 1
             ORDER BY mi.sort, mi.id',
            [$slug]
        );
    }

    /** Items the given session is allowed to see. */
    public static function visible(string $slug, Session $session): array
    {
        $user = $session->user();
        $rank = Rbac::rank($user);
        $out  = [];
        foreach (self::items($slug) as $it) {
            if ($it['action'] === 'divider') {
                $out[] = $it;
                continue;
            }
            if ((int) $it['min_role_rank'] > $rank) {
                continue;
            }
            if (!empty($it['min_permission']) && !Rbac::can($user, $it['min_permission'])) {
                continue;
            }
            $out[] = $it;
        }
        // drop leading/trailing/double dividers
        return self::tidyDividers($out);
    }

    public static function resolve(string $slug, Session $session, string $key): ?array
    {
        $key = strtoupper(trim($key));
        foreach (self::visible($slug, $session) as $it) {
            if ($it['action'] !== 'divider' && strtoupper($it['hotkey']) === $key && $key !== '') {
                return $it;
            }
        }
        return null;
    }

    /**
     * Draw the menu into $frame. Returns the visible item list (client uses it
     * for arrow-key navigation).
     * @return list<array<string,mixed>>
     */
    public static function render(Frame $frame, string $slug, Session $session, array $ctx): array
    {
        $menu  = self::load($slug) ?? ['title' => ucfirst($slug), 'columns' => 2, 'prompt' => 'Command', 'header_screen' => null];
        $items = self::visible($slug, $session);

        $frame->view('menu')->title($menu['title'])->mode('menu')->prompt($menu['prompt'] ?: 'Command');

        $right = 'Node ' . $session->node . ' · ' . $session->ipPhone;
        $frame->header($menu['title'], $right)->blank();

        if (!empty($menu['header_screen'])) {
            $scr = Screen::load($menu['header_screen']);
            if ($scr) {
                $frame->block($scr['body'], $scr['content_type'], $ctx)->blank();
            }
        }

        $cols = max(1, (int) $menu['columns']);
        $cells = [];
        foreach ($items as $it) {
            if ($it['action'] === 'divider') {
                // flush current row, add a spacer
                while (count($cells) % $cols !== 0) {
                    $cells[] = null;
                }
                $cells[] = '__RULE__';
                continue;
            }
            $hot   = str_pad($it['hotkey'], 3, ' ', STR_PAD_LEFT);
            $label = $it['label'];
            $cells[] = '|08[|15' . trim($hot) . '|08] |07' . $label;
        }

        $perCol = (int) ceil(count($cells) / $cols);
        // lay out column-major so hotkeys read down then across (classic BBS)
        $rows = [];
        for ($r = 0; $r < $perCol; $r++) {
            $line = '   ';
            for ($c = 0; $c < $cols; $c++) {
                $idx = $c * $perCol + $r;
                $cell = $cells[$idx] ?? '';
                if ($cell === '__RULE__') {
                    $cell = '|08' . str_repeat('─', 34);
                }
                $line .= self::padCell($cell, intdiv(Frame::width() - 4, $cols));
            }
            $rows[] = rtrim($line);
        }
        foreach ($rows as $row) {
            $frame->pipe($row);
        }

        $frame->blank();
        $desc = '';
        foreach ($items as $it) {
            if (($it['description'] ?? '') !== '' && $it['action'] !== 'divider') {
                $desc = $it['description'];
                break;
            }
        }
        $frame->footer('Type the letter · ↑↓ move · ENTER select · ESC back');

        $picks = array_values(array_filter($items, static fn ($i) => $i['action'] !== 'divider'));
        $frame->meta([
            'items' => array_map(static fn ($i) => [
                'hotkey'      => $i['hotkey'],
                'label'       => $i['label'],
                'description' => $i['description'],
            ], $picks),
            'menu' => $slug,
        ]);
        return $picks;
    }

    private static function padCell(string $pipeCell, int $width): string
    {
        $plain = preg_replace('/\|\d{2}/', '', $pipeCell) ?? $pipeCell;
        $vis   = mb_strlen($plain);
        $pad   = max(1, $width - $vis);
        return $pipeCell . str_repeat(' ', $pad);
    }

    private static function tidyDividers(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if ($it['action'] === 'divider') {
                if (!$out || end($out)['action'] === 'divider') {
                    continue;
                }
            }
            $out[] = $it;
        }
        while ($out && end($out)['action'] === 'divider') {
            array_pop($out);
        }
        return $out;
    }
}
