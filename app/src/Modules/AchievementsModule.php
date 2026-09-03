<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Achievements;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;

/**
 * Trophy case. Opening it re-syncs the caller's badges, then lists the whole
 * catalogue grouped by category: earned ones lit with a date, locked ones dim,
 * secret-and-locked ones hidden as "???".
 */
final class AchievementsModule extends Module
{
    private const CATS = [
        'getting-started' => 'Getting Started',
        'community'       => 'Community',
        'games'           => 'Games',
        'night-city'      => 'Night City',
        'dedication'      => 'Dedication',
        'secret'         => 'Secret',
    ];

    public static function slugs(): array
    {
        return ['achievements'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }

        $uid = (int) ($e->session->userId ?? 0);
        if ($uid <= 0) {
            return Frame::make('screen')->view('screen')->title('Achievements')->mode('pager')
                ->header('Trophy Case')->blank()
                ->pipe('|12   Guests do not earn trophies. Log in with a handle first.')
                ->footer('Q back');
        }

        $fresh = Achievements::sync($uid);
        $sum   = Achievements::summary($uid);
        $rows  = Achievements::forUser($uid);

        $pct = $sum['total'] > 0 ? (int) round(100 * $sum['earned'] / $sum['total']) : 0;
        $barW = 40;
        $fill = (int) round($barW * $pct / 100);
        $bar  = '|10' . str_repeat("\u{2588}", $fill) . '|08' . str_repeat("\u{2591}", $barW - $fill);

        $f = Frame::make('screen')->view('screen')->title('Achievements')->mode('menu')
            ->header('Trophy Case', $e->session->handle())->blank()
            ->pipe(sprintf('|07   Unlocked  |15%d|07 / %d   badges     |15%d|07 / %d   points',
                $sum['earned'], $sum['total'], $sum['earned_points'], $sum['points']))
            ->pipe('|07   [' . $bar . '|07] |15' . $pct . '%')
            ->blank();

        if ($fresh) {
            foreach ($fresh as $name) {
                $f->pipe("|14   \u{2605} NEW  -  " . $name);
            }
            $f->blank();
        }

        // group by category, in a fixed order
        $byCat = [];
        foreach ($rows as $r) {
            $byCat[$r['category']][] = $r;
        }
        foreach (self::CATS as $ckey => $clabel) {
            $list = $byCat[$ckey] ?? [];
            if (!$list) {
                continue;
            }
            $got = count(array_filter($list, static fn ($r) => $r['earned_at'] !== null));
            $f->pipe(sprintf('|11   %s |08(%d/%d)', strtoupper($clabel), $got, count($list)));
            foreach ($list as $r) {
                $earned = $r['earned_at'] !== null;
                $secret = (int) $r['is_secret'] === 1 && !$earned;
                if ($secret) {
                    $f->pipe('|08     [ ? ]  ' . str_repeat('?', 12) . '   |08a secret badge');
                    continue;
                }
                $icon = mb_substr((string) $r['icon'], 0, 2);
                if ($earned) {
                    $when = date('j M Y', strtotime((string) $r['earned_at']));
                    $f->pipe(sprintf('|10     [%s|10]  |15%-22s |07%-40s |08%s',
                        $icon, mb_substr($r['name'], 0, 22), mb_substr($r['description'], 0, 40), $when));
                } else {
                    $f->pipe(sprintf('|08     [%s]  %-22s %s',
                        $icon, mb_substr($r['name'], 0, 22), mb_substr($r['description'], 0, 44)));
                }
            }
            $f->blank();
        }

        return $f->footer('SPACE / arrows to scroll  ' . "\u{00b7}" . '  Q back');
    }
}
