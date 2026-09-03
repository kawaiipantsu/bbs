<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\AnsiRenderer;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * WEATHER TERMINAL (slug `weather`).
 *
 * An old-school "dial the weather satellite" screen. Asks for a location,
 * fetches current conditions from wttr.in (coloured ANSI art), caches each
 * location for 30 minutes in `weather_cache`, and frames the readout in a
 * rounded pipe-coded box. Degrades gracefully: on a fetch failure it serves
 * stale cache if it has any, otherwise a friendly "satellite is down" note -
 * never a fatal error.
 *
 * Keys:  L change location · R refresh (bypass cache) · Q / ESC leave.
 */
final class WeatherModule extends Module
{
    private const TTL_MIN   = 30;
    private const BOX_INNER = 54;
    private const BOX_PAD   = 4;

    /** @var array<string,string> quick-pick number => proper place name */
    private const PICKS = [
        '1' => 'Copenhagen',
        '2' => 'Aarhus',
        '3' => 'Odense',
        '4' => 'Aalborg',
    ];

    public static function slugs(): array
    {
        return ['weather'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key  = strtoupper((string) ($in['key'] ?? ''));
        $line = trim((string) ($in['input'] ?? ''));

        // Q / ESC always leaves.
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }

        $screen = (string) ($st['screen'] ?? '');

        // First entry: jump straight to the last location if we remember one,
        // otherwise ask for a location.
        if ($screen === '') {
            if (($st['loc'] ?? '') !== '') {
                $st['screen'] = 'show';
                return $this->show($st, false);
            }
            $st['screen'] = 'ask';
            return $this->ask($st);
        }

        if ($screen === 'ask') {
            if ($line === '') {
                return $this->ask($st);
            }
            $st['loc']    = $this->resolvePick($line);
            $st['screen'] = 'show';
            return $this->show($st, false);
        }

        // screen === 'show'
        if ($key === 'L') {
            $st['screen'] = 'ask';
            return $this->ask($st);
        }
        if ($key === 'R') {
            return $this->show($st, true);
        }
        return $this->show($st, false);
    }

    // -----------------------------------------------------------------
    //  Screens
    // -----------------------------------------------------------------
    private function ask(array $st): Frame
    {
        $f = Frame::make('screen')->title('Weather')->mode('line')
            ->header('Weather Terminal', 'satellite uplink')
            ->blank()
            ->pipe('|11   ~~~   |15W E A T H E R   T E R M I N A L|11   ~~~')
            ->pipe('|08   ' . str_repeat('-', 46))
            ->blank()
            ->pipe('|07   Enter a city or place name for current conditions.')
            ->blank()
            ->pipe('|08   Quick picks:')
            ->pipe('|07     |15[1]|07 Copenhagen      |15[2]|07 Aarhus')
            ->pipe('|07     |15[3]|07 Odense          |15[4]|07 Aalborg')
            ->blank();

        if (($st['loc'] ?? '') !== '') {
            $f->pipe('|08   Last location: |07' . $st['loc'])->blank();
        }

        return $f->prompt('Location')->footer('type a name or 1-4 · ESC / Q back');
    }

    private function show(array &$st, bool $force): Frame
    {
        $label = (string) ($st['loc'] ?? '');
        if ($label === '') {
            $st['screen'] = 'ask';
            return $this->ask($st);
        }

        $k = $this->cacheKey($label);

        $row = $force ? null : Db::one(
            'SELECT * FROM weather_cache WHERE loc = ? AND fetched_at > (NOW() - INTERVAL ' . self::TTL_MIN . ' MINUTE)',
            [$k]
        );

        if ($row) {
            return $this->render($label, (string) $row['body'],
                'cached · ' . $this->ageMin((string) $row['fetched_at']) . ' min ago', 8);
        }

        $raw = $this->fetch($label);
        if ($raw !== null && trim($raw) !== '') {
            $this->store($k, $label, $raw);
            return $this->render($label, $raw, 'transmitted ' . date('H:i') . ' local', 8);
        }

        // Fetch failed - serve stale cache of any age if we have it.
        $stale = Db::one('SELECT * FROM weather_cache WHERE loc = ?', [$k]);
        if ($stale) {
            return $this->render($label, (string) $stale['body'],
                'STALE · last contact ' . $this->ageMin((string) $stale['fetched_at']) . ' min ago', 12);
        }

        return Frame::make('screen')->title('Weather')->mode('menu')
            ->header('Weather · ' . $label, date('H:i') . ' local')
            ->blank(2)
            ->pipe('|12   The weather satellite is down. Try again shortly.')
            ->blank()
            ->pipe('|08   No cached reading for "' . $label . '" yet.')
            ->footer('L change location · R retry · Q back');
    }

    private function render(string $label, string $body, string $status, int $statusColor): Frame
    {
        $f = Frame::make('screen')->title('Weather')->mode('menu')
            ->header('Weather · ' . $label, date('H:i') . ' local')
            ->blank();

        $inner  = self::BOX_INNER;
        $margin = str_repeat(' ', self::BOX_PAD);

        /** @var list<list<array<string,mixed>>> $content */
        $content   = [];
        $content[] = [self::r(' ' . mb_strtoupper($label), 15)];
        $content[] = [self::r(' ' . $status, $statusColor)];
        $content[] = [];
        foreach (AnsiRenderer::render(self::tidy($body), 'ansi') as $runs) {
            $content[] = $runs;
        }
        $content[] = [];
        $content[] = [self::r('    N            ~ ~  ', 8)];
        $content[] = [self::r('  W -+- E       ~ ~ ~ ', 8)];
        $content[] = [self::r('    S            ~ ~  ', 8)];

        $f->pipe('|11' . $margin . '╭' . str_repeat('─', $inner + 2) . '╮');
        foreach ($content as $runs) {
            $vis = 0;
            foreach ($runs as $x) {
                $vis += mb_strlen((string) $x['s']);
            }
            if ($vis > $inner) {
                $runs = self::clip($runs, $inner);
                $vis  = $inner;
            }
            $row = array_merge(
                [self::r($margin . '│ ', 11)],
                $runs,
                [self::r(str_repeat(' ', max(0, $inner - $vis)), 7), self::r(' │', 11)]
            );
            $f->raw([$row]);
        }
        $f->pipe('|11' . $margin . '╰' . str_repeat('─', $inner + 2) . '╯');

        return $f->blank()->footer('L change location · R refresh · Q back');
    }

    // -----------------------------------------------------------------
    //  Fetch + cache
    // -----------------------------------------------------------------
    private function fetch(string $loc): ?string
    {
        $url = 'https://wttr.in/' . rawurlencode($loc) . '?0&Q&lang=en';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_USERAGENT      => 'curl/8',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($body) && $body !== '' && $code < 400) {
                return $body;
            }
            error_log("[BBS] weather fetch $url -> HTTP $code");
            return null;
        }

        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'timeout' => 6,
            'header'  => "User-Agent: curl/8\r\n",
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) && $body !== '' ? $body : null;
    }

    private function store(string $k, string $label, string $body): void
    {
        $data = [
            'label'      => mb_substr($label, 0, 120),
            'body'       => $body,
            'fetched_at' => date('Y-m-d H:i:s'),
        ];
        try {
            if (Db::val('SELECT 1 FROM weather_cache WHERE loc = ?', [$k]) !== null) {
                Db::update('weather_cache', $data, ['loc' => $k]);
            } else {
                Db::insert('weather_cache', ['loc' => $k] + $data);
            }
        } catch (\Throwable $ex) {
            error_log('[BBS] weather cache write failed: ' . $ex->getMessage());
        }
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------
    private function resolvePick(string $s): string
    {
        $s = trim($s);
        if (isset(self::PICKS[$s])) {
            return self::PICKS[$s];
        }
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return mb_substr($s, 0, 100);
    }

    private function cacheKey(string $label): string
    {
        $s = mb_strtolower(trim($label));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return mb_substr($s, 0, 120);
    }

    private function ageMin(string $ts): int
    {
        $t = strtotime($ts) ?: time();
        return max(0, (int) floor((time() - $t) / 60));
    }

    /** Trim blank edges from the wttr.in block so it sits tight in the box. */
    private static function tidy(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        while ($lines && trim(preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]) ?? '') === '') {
            array_shift($lines);
        }
        while ($lines && trim(preg_replace('/\x1b\[[0-9;]*m/', '', end($lines)) ?? '') === '') {
            array_pop($lines);
        }
        return implode("\n", array_slice($lines, 0, 12));
    }

    /** @return array<string,mixed> */
    private static function r(string $s, int $f = 7, int $b = 0): array
    {
        return ['s' => $s, 'f' => $f, 'b' => $b, 'o' => $f >= 8, 'k' => false];
    }

    /**
     * Clip a run list to a maximum visible width.
     *
     * @param  list<array<string,mixed>> $runs
     * @return list<array<string,mixed>>
     */
    private static function clip(array $runs, int $max): array
    {
        $out  = [];
        $used = 0;
        foreach ($runs as $x) {
            $len = mb_strlen((string) $x['s']);
            if ($used + $len <= $max) {
                $out[] = $x;
                $used += $len;
                continue;
            }
            $take = $max - $used;
            if ($take > 0) {
                $x['s'] = mb_substr((string) $x['s'], 0, $take);
                $out[]  = $x;
            }
            break;
        }
        return $out;
    }
}
