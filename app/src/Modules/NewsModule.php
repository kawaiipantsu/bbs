<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * News wire. Reads RSS-fed rows from `news_items` (populated by contrib/worker.php).
 * One module, category taken from the slug: news.it / news.hacking / news.tech /
 * news.entertainment.
 */
final class NewsModule extends Module
{
    private const CATS = [
        'news.it'            => ['it', 'IT NEWS WIRE'],
        'news.hacking'       => ['hacking', 'HACKING / INFOSEC WIRE'],
        'news.tech'          => ['tech', 'TECH NEWS WIRE'],
        'news.entertainment' => ['entertainment', 'ENTERTAINMENT WIRE'],
    ];

    public static function slugs(): array
    {
        return array_keys(self::CATS);
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        [$cat, $title] = self::CATS[$slug] ?? ['it', 'NEWS'];
        $items = Db::all(
            'SELECT id, source, title, url, summary, published_at
             FROM news_items WHERE category = ? ORDER BY published_at DESC, id DESC LIMIT 60',
            [$cat]
        );

        $st['sel'] ??= 0;
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($items) {
            if ($key === 'DOWN' || $key === 'J') {
                $st['sel'] = min(count($items) - 1, $st['sel'] + 1);
            } elseif ($key === 'UP' || $key === 'K') {
                $st['sel'] = max(0, $st['sel'] - 1);
            } elseif ($key === "\r" || $key === "\n" || $key === 'ENTER' || $cmd === 'open') {
                $target = $items[$st['sel']]['url'] ?? '';
                if ($target) {
                    return Frame::make('redirect')->mode('redirect')->meta(['url' => $target, 'newtab' => true])
                        ->title($title);
                }
            }
        }

        $f = Frame::make('screen')->title($title)->mode('menu')
            ->header($title, 'updated ' . ($items ? $this->age($items[0]['published_at']) : 'never'))
            ->blank();

        if (!$items) {
            $f->pipe('|08   No headlines cached yet. The news worker runs on a schedule -')
              ->pipe('|08   a SysOp can force a refresh from  SysOp Area -> News & Feeds.');
            return $f->footer('ESC / Q  back');
        }

        foreach ($items as $i => $it) {
            $marker = $i === $st['sel'] ? '|12>' : '|08 ';
            $src = str_pad('[' . mb_substr($it['source'], 0, 14) . ']', 16);
            $when = str_pad($this->age($it['published_at']), 6);
            $fg = $i === $st['sel'] ? '|15' : '|07';
            $f->pipe(sprintf('%s |08%s %s%s %s%s', $marker, $when, '|09', $src, $fg, $this->clip($it['title'], 92)));
        }
        $sel = $items[$st['sel']];
        $f->blank()->rule()
          ->pipe('|07 ' . $this->clip($sel['summary'] ?: $sel['title'], 118))
          ->pipe('|08 ' . $sel['url']);

        return $f->footer('↑↓ move · ENTER open in new tab · Q back');
    }

    private function clip(string $s, int $n): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }

    private function age(?string $ts): string
    {
        if (!$ts) {
            return '—';
        }
        $d = time() - strtotime($ts);
        if ($d < 3600) {
            return max(1, intdiv($d, 60)) . 'm';
        }
        if ($d < 86400) {
            return intdiv($d, 3600) . 'h';
        }
        return intdiv($d, 86400) . 'd';
    }
}
