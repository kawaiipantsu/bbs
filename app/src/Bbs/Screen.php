<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Core\Db;

/**
 * Stored screens (`screens` table). Editable in SysOp -> Screens & Menus.
 */
final class Screen
{
    /** @return array<string,mixed>|null */
    public static function load(string $slug): ?array
    {
        return Db::one('SELECT * FROM screens WHERE slug = ?', [$slug]);
    }

    /**
     * Render a stored screen into a Frame.
     * @param array<string,scalar|null> $ctx
     */
    public static function render(Frame $frame, string $slug, array $ctx, string $missingTitle = 'Screen'): Frame
    {
        $scr = self::load($slug);
        if (!$scr) {
            return $frame->view('screen')->title($missingTitle)->mode('pager')
                ->header($missingTitle)->blank()
                ->pipe('|12   Screen "' . $slug . '" is not defined.')
                ->pipe('|08   A SysOp can create it in Screens & Menus.')
                ->footer('ESC / Q  back');
        }
        return $frame->view('screen')->title($scr['title'] ?: $missingTitle)->mode('pager')
            ->block($scr['body'], $scr['content_type'], $ctx)
            ->footer('SPACE page · B back a page · Q / ESC return');
    }
}
