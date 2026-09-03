<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;

/**
 * CHIPTUNE RADIO - a station of real tracker modules (.mod / .xm / .s3m / .it)
 * streamed on the client through libopenmpt (WASM). The module renders the dial
 * and emits `meta.chiptune` commands; the browser (html/js/chiptune.js, wired in
 * app.js) does the actual playback, auto-advances and loops.
 *
 * Three screens driven by a small $st state machine:
 *   cats     - list of categories ("crates") + the playlist
 *   tracks   - the modules inside one category
 *   playlist - the user-built queue
 *
 * State ($st):
 *   screen   - 'cats' | 'tracks' | 'playlist'          (default 'cats')
 *   cat      - current category id (on the tracks screen)
 *   sel      - selection index within the current list (clamped every step)
 *   playlist - list<int> of GLOBAL track indices (0-based into the flat catalogue)
 *   np       - what we last told the client to play, for the NOW PLAYING panel
 *              (['title'=>, 'artist'=>, 'queue'=>bool, 'more'=>int]) or null
 *
 * The client can't fetch /media/tracks/manifest.json (static *.json is denied by
 * .htaccess) so every frame carries the whole flat catalogue in
 * `meta.chiptuneCatalog` plus the live queue in `meta.chiptunePlaylist`.
 */
final class ChiptuneModule extends Module
{
    private const MANIFEST = '/html/media/tracks/manifest.json';
    private const RULE_W    = 96;
    private const PAGE_ROWS = 24;

    public static function slugs(): array
    {
        return ['chiptune'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $cat   = $this->catalogue();
        $flat  = $cat['flat'];        // list<array{file,title,artist,format,cat,year}>
        $cats  = $cat['cats'];        // list<array{id,name,idx:list<int>}>
        $count = count($flat);

        // ---- key normalisation --------------------------------------------
        $key = (string) ($in['key'] ?? '');
        $key = match ($key) {
            "\x1b"  => 'ESC',
            "\r", "\n" => 'ENTER',
            ' '     => 'SPACE',
            "\x08", "\x7f" => 'DEL',
            default => strtoupper($key),
        };

        // ---- state ------------------------------------------------------
        $screen = (string) ($st['screen'] ?? 'cats');
        if (!in_array($screen, ['cats', 'tracks', 'playlist'], true)) {
            $screen = 'cats';
        }
        $sel = (int) ($st['sel'] ?? 0);
        $np  = is_array($st['np'] ?? null) ? $st['np'] : null;

        // sanitise the persisted playlist to valid global indices
        $pl = [];
        foreach ((array) ($st['playlist'] ?? []) as $g) {
            $g = (int) $g;
            if ($g >= 0 && $g < $count) {
                $pl[] = $g;
            }
        }

        // resolve the current category
        $catId  = (string) ($st['cat'] ?? ($cats[0]['id'] ?? ''));
        $curCat = null;
        foreach ($cats as $c) {
            if ($c['id'] === $catId) {
                $curCat = $c;
                break;
            }
        }
        if ($curCat === null) {
            $curCat = $cats[0] ?? ['id' => '', 'name' => '', 'idx' => []];
            $catId  = $curCat['id'];
        }

        // ---- empty catalogue -----------------------------------------
        if ($count === 0) {
            if ($key === 'Q' || $key === 'ESC') {
                return $e->exitModule();
            }
            return Frame::make('screen')->title('Chiptune Radio')->mode('game')
                ->header('CHIPTUNE RADIO', 'offline')->blank()
                ->pipe('|12   No modules found.')
                ->pipe('|08   Expected a manifest at html/media/tracks/manifest.json')
                ->footer('Q / ESC  back');
        }

        // ---- input ----------------------------------------------------
        $cmd   = null;    // meta.chiptune payload
        $flash = null;    // one-shot status line

        // stop works from anywhere
        if ($key === 'S') {
            $np  = null;
            $cmd = ['action' => 'stop'];
        }

        if ($screen === 'cats') {
            $n = max(1, count($cats));
            if ($key === 'Q' || $key === 'ESC') {
                return $e->exitModule();
            } elseif ($key === 'P') {
                $screen = 'playlist';
                $sel = 0;
            } elseif ($key === 'UP') {
                $sel = ($sel - 1 + $n) % $n;
            } elseif ($key === 'DOWN' || $key === 'SPACE') {
                $sel = ($sel + 1) % $n;
            } elseif (ctype_digit($key) && $key !== '0') {
                $d = (int) $key - 1;
                if ($d < count($cats)) {
                    $catId  = $cats[$d]['id'];
                    $screen = 'tracks';
                    $sel = 0;
                }
            } elseif ($key === 'ENTER') {
                $sel = max(0, min($sel, count($cats) - 1));
                $catId  = $cats[$sel]['id'];
                $screen = 'tracks';
                $sel = 0;
            }
        } elseif ($screen === 'tracks') {
            $ids = $curCat['idx'];
            $n   = count($ids);
            if ($key === 'ESC' || $key === 'B' || $key === 'Q') {
                $screen = 'cats';
                $sel = 0;
            } elseif ($key === 'L' || $key === 'P') {
                $screen = 'playlist';
                $sel = 0;
            } elseif ($n > 0 && ctype_digit($key) && $key !== '0') {
                $d = (int) $key - 1;
                if ($d < $n) {
                    $sel = $d;
                }
            } elseif ($key === 'UP') {
                $sel = $n > 0 ? ($sel - 1 + $n) % $n : 0;
            } elseif ($key === 'DOWN' || $key === 'SPACE') {
                $sel = $n > 0 ? ($sel + 1) % $n : 0;
            } elseif ($key === 'ENTER' && $n > 0) {
                $sel = max(0, min($sel, $n - 1));
                $g = $ids[$sel];
                $t = $flat[$g];
                $np  = ['title' => $t['title'], 'artist' => $t['artist'], 'queue' => false];
                $cmd = [
                    'action' => 'play',
                    'index'  => $g,
                    'title'  => $t['title'],
                    'artist' => $t['artist'],
                    'format' => $t['format'],
                ];
            } elseif ($key === 'A' && $n > 0) {
                $sel = max(0, min($sel, $n - 1));
                $g = $ids[$sel];
                $pl[] = $g;                       // dupes allowed
                $flash = 'added to playlist: ' . $flat[$g]['title'];
            }
        } else { // playlist
            $n = count($pl);
            if ($key === 'ESC' || $key === 'B' || $key === 'Q') {
                $screen = 'cats';
                $sel = 0;
            } elseif ($key === 'UP') {
                $sel = $n > 0 ? ($sel - 1 + $n) % $n : 0;
            } elseif ($key === 'DOWN' || $key === 'SPACE') {
                $sel = $n > 0 ? ($sel + 1) % $n : 0;
            } elseif (($key === 'P' || $key === 'ENTER') && $n > 0) {
                $first = $flat[$pl[0]];
                $np  = [
                    'title'  => $first['title'],
                    'artist' => $first['artist'],
                    'queue'  => true,
                    'more'   => $n - 1,
                ];
                $cmd = ['action' => 'playqueue', 'queue' => array_values($pl), 'start' => 0];
            } elseif ($key === 'R' && $n > 1) {
                shuffle($pl);                    // Fisher-Yates, in place
                $sel = 0;
            } elseif ($key === 'C') {
                $pl = [];
                $sel = 0;
            } elseif (($key === 'X' || $key === 'DEL') && $n > 0) {
                $sel = max(0, min($sel, $n - 1));
                array_splice($pl, $sel, 1);
                if ($sel >= count($pl)) {
                    $sel = max(0, count($pl) - 1);
                }
            }
        }

        // a cats-screen ENTER / digit may have just switched category - re-resolve
        // $curCat from $catId so THIS frame already shows the right crate
        $curCat = $cats[0] ?? ['id' => '', 'name' => '', 'idx' => []];
        foreach ($cats as $c) {
            if ($c['id'] === $catId) {
                $curCat = $c;
                break;
            }
        }

        // ---- persist --------------------------------------------------
        $st['screen']   = $screen;
        $st['cat']      = $catId;
        $st['sel']      = $sel;
        $st['playlist'] = array_values($pl);
        $st['np']       = $np;

        // ---- render -------------------------------------------------
        $f = match ($screen) {
            'tracks'   => $this->renderTracks($flat, $curCat, $pl, $sel, $np, $flash),
            'playlist' => $this->renderPlaylist($flat, $pl, $sel, $np, $flash),
            default    => $this->renderCats($cats, $count, $pl, $sel, $np, $flash),
        };

        // ---- meta --------------------------------------------------
        $f->meta([
            'chiptuneCatalog' => array_map(
                static fn (array $t): array => [
                    'file'   => $t['file'],
                    'title'  => $t['title'],
                    'artist' => $t['artist'],
                    'format' => $t['format'],
                    'cat'    => $t['cat'],
                ],
                $flat
            ),
            'chiptunePlaylist' => array_values($pl),
        ]);
        if ($cmd !== null) {
            $f->meta(['chiptune' => $cmd]);
        }

        return $f;
    }

    // -----------------------------------------------------------------
    //  Renderers
    // -----------------------------------------------------------------

    /**
     * @param list<array{id:string,name:string,idx:list<int>}> $cats
     * @param list<int> $pl
     */
    private function renderCats(array $cats, int $count, array $pl, int $sel, ?array $np, ?string $flash): Frame
    {
        $n   = count($cats);
        $sel = $n > 0 ? max(0, min($sel, $n - 1)) : 0;

        $f = Frame::make('screen')->title('Chiptune Radio')->mode('game')
            ->header('CHIPTUNE RADIO', $count . ' modules on rotation')->blank();

        $f->pipe('|08   Real tracker modules streamed through libopenmpt (WASM).  Pick a crate.')
          ->blank()
          ->pipe('   ' . $this->npLine($np))
          ->blank()
          ->pipe('|08   ' . str_repeat('-', self::RULE_W))
          ->blank();

        foreach ($cats as $i => $c) {
            $marker = $i === $sel ? '|10>' : '|08 ';
            $col    = $i === $sel ? '|15' : '|07';
            $cnt    = count($c['idx']);
            $f->pipe(sprintf(
                '  %s |08[%s%d|08] %s%-26s |08%d module%s',
                $marker, $col, $i + 1, $col, $this->clip($c['name'], 26),
                $cnt, $cnt === 1 ? '' : 's'
            ));
        }

        $f->blank()
          ->pipe(sprintf('    |08[|15P|08] |14%-26s |08%d queued', 'Playlist', count($pl)))
          ->blank();

        if ($flash !== null) {
            $f->pipe('   |10' . $flash)->blank();
        }
        $f->pipe('|08   1-9 / UP-DOWN + ENTER open crate    P playlist    S stop    Q quit');

        return $f->footer('digits/UP-DOWN select · ENTER open · P playlist · S stop · Q quit');
    }

    /**
     * @param list<array<string,mixed>> $flat
     * @param array{id:string,name:string,idx:list<int>} $curCat
     * @param list<int> $pl
     */
    private function renderTracks(array $flat, array $curCat, array $pl, int $sel, ?array $np, ?string $flash): Frame
    {
        $ids = $curCat['idx'];
        $n   = count($ids);
        $sel = $n > 0 ? max(0, min($sel, $n - 1)) : 0;

        $f = Frame::make('screen')->title('Chiptune Radio')->mode('game')
            ->header('CHIPTUNE RADIO / ' . $curCat['name'], $n . ($n === 1 ? ' module' : ' modules'))
            ->blank();

        $f->pipe('   ' . $this->npLine($np))
          ->blank()
          ->pipe('|08   ' . str_repeat('-', self::RULE_W))
          ->blank();

        if ($n === 0) {
            $f->pipe('|08   (this crate is empty)');
        } else {
            [$start, $slice] = $this->window($ids, $sel);
            foreach ($slice as $row => $g) {
                $t      = $flat[$g];
                $marker = $row === $sel ? '|10>' : '|08 ';
                $col    = $row === $sel ? '|15' : '|07';
                $yr     = $t['year'] !== null ? ' (' . $t['year'] . ')' : '';
                $who    = $t['artist'] !== '' ? $t['artist'] . $yr : '-';
                $onPl   = in_array($g, $pl, true) ? ' |10+' : '';
                $f->pipe(sprintf(
                    '  %s |08%2d. %s%-32s |09%-24s |08%-4s%s',
                    $marker, $row + 1, $col,
                    $this->clip($t['title'], 32),
                    $this->clip($who, 24),
                    $t['format'], $onPl
                ));
            }
            if (count($ids) > count($slice)) {
                $f->blank()->pipe(sprintf(
                    '|08   showing %d-%d of %d  (UP-DOWN to scroll)',
                    $start + 1, $start + count($slice), count($ids)
                ));
            }
        }

        $f->blank();
        if ($flash !== null) {
            $f->pipe('   |10' . $flash)->blank();
        }
        $f->pipe('|08   UP-DOWN / 1-9 select   ENTER play   A add   L playlist   B back');

        return $f->footer('ENTER play · A add · L playlist · B back · S stop');
    }

    /**
     * @param list<array<string,mixed>> $flat
     * @param list<int> $pl
     */
    private function renderPlaylist(array $flat, array $pl, int $sel, ?array $np, ?string $flash): Frame
    {
        $n   = count($pl);
        $sel = $n > 0 ? max(0, min($sel, $n - 1)) : 0;

        $f = Frame::make('screen')->title('Chiptune Radio')->mode('game')
            ->header('CHIPTUNE RADIO / Playlist', $n . ($n === 1 ? ' track queued' : ' tracks queued'))
            ->blank();

        $f->pipe('   ' . $this->npLine($np))
          ->blank()
          ->pipe('|08   ' . str_repeat('-', self::RULE_W))
          ->blank();

        if ($n === 0) {
            $f->pipe('|14   Your playlist is empty.')
              ->blank()
              ->pipe('|08   Open a crate, highlight a module and press |15A|08 to queue it up.')
              ->pipe('|08   Come back here and press |15ENTER|08 to play the whole queue on a loop.');
        } else {
            [$start, $slice] = $this->window($pl, $sel);
            foreach ($slice as $row => $g) {
                $t      = $flat[$g];
                $marker = $row === $sel ? '|10>' : '|08 ';
                $col    = $row === $sel ? '|15' : '|07';
                $who    = $t['artist'] !== '' ? $t['artist'] : '-';
                $f->pipe(sprintf(
                    '  %s |08%2d. %s%-34s |09%-22s |08%s',
                    $marker, $row + 1, $col,
                    $this->clip($t['title'], 34),
                    $this->clip($who, 22),
                    $t['format']
                ));
            }
            if (count($pl) > count($slice)) {
                $f->blank()->pipe(sprintf(
                    '|08   showing %d-%d of %d  (UP-DOWN to scroll)',
                    $start + 1, $start + count($slice), count($pl)
                ));
            }
        }

        $f->blank();
        if ($flash !== null) {
            $f->pipe('   |10' . $flash)->blank();
        }
        $f->pipe('|08   ENTER play the queue (loops)   R shuffle   C clear   X remove   B back');

        return $f->footer('ENTER play  A add  R shuffle  C clear  X remove  B back');
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function npLine(?array $np): string
    {
        if ($np === null) {
            return '|18|00 IDLE |CL |08nothing on the wire · the background bed is playing';
        }
        $s = '|19|00 NOW PLAYING |CL |15' . $np['title'];
        if (($np['artist'] ?? '') !== '') {
            $s .= ' |08by |07' . $np['artist'];
        }
        if (!empty($np['queue'])) {
            $more = (int) ($np['more'] ?? 0);
            $s .= $more > 0 ? ' |10[+' . $more . ' queued]' : ' |10[queue]';
        }
        return $s;
    }

    /**
     * A scrolling window over a list, centred on $sel.
     *
     * @param list<int> $list
     * @return array{0:int,1:array<int,int>}  [startOffset, slice keyed by absolute row]
     */
    private function window(array $list, int $sel): array
    {
        $total = count($list);
        if ($total <= self::PAGE_ROWS) {
            return [0, $list];
        }
        $start = max(0, min($sel - intdiv(self::PAGE_ROWS, 2), $total - self::PAGE_ROWS));
        $slice = [];
        for ($i = $start; $i < $start + self::PAGE_ROWS; $i++) {
            $slice[$i] = $list[$i];
        }
        return [$start, $slice];
    }

    /**
     * Parse the manifest into a flat catalogue with stable global indices plus a
     * category -> [globalIdx,...] map.
     *
     * @return array{
     *   flat: list<array{file:string,title:string,artist:string,format:string,cat:string,year:?int}>,
     *   cats: list<array{id:string,name:string,idx:list<int>}>
     * }
     */
    private function catalogue(): array
    {
        $path = dirname(__DIR__, 3) . self::MANIFEST;
        $raw  = is_file($path) ? file_get_contents($path) : false;
        $data = $raw !== false ? json_decode($raw, true) : null;

        $categories = (is_array($data) && isset($data['categories']) && is_array($data['categories']))
            ? $data['categories']
            : [];

        // legacy fallback: a flat { "tracks": [...] } manifest
        if (!$categories && is_array($data) && isset($data['tracks']) && is_array($data['tracks'])) {
            $categories = [['id' => 'all', 'name' => 'All Tracks', 'tracks' => $data['tracks']]];
        }

        $flat = [];
        $cats = [];
        $g    = 0;

        foreach ($categories as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cid    = trim((string) ($c['id'] ?? ('cat' . count($cats))));
            if ($cid === '') {
                $cid = 'cat' . count($cats);
            }
            $cname  = trim((string) ($c['name'] ?? $cid));
            $tracks = (isset($c['tracks']) && is_array($c['tracks'])) ? $c['tracks'] : [];
            $idx    = [];

            foreach ($tracks as $t) {
                if (!is_array($t) || !isset($t['file']) || trim((string) $t['file']) === '') {
                    continue;
                }
                $file  = trim((string) $t['file']);
                $title = trim((string) ($t['title'] ?? ''));
                $year  = isset($t['year']) && (int) $t['year'] > 0 ? (int) $t['year'] : null;

                $flat[] = [
                    'file'   => $file,
                    'title'  => $title !== '' ? $title : $file,
                    'artist' => trim((string) ($t['artist'] ?? '')),
                    'format' => $this->fmt($t, $file),
                    'cat'    => $cid,
                    'year'   => $year,
                ];
                $idx[] = $g++;
            }

            $cats[] = ['id' => $cid, 'name' => $cname !== '' ? $cname : $cid, 'idx' => $idx];
        }

        return ['flat' => $flat, 'cats' => $cats];
    }

    /** @param array<string,mixed> $t */
    private function fmt(array $t, string $file): string
    {
        $s = strtoupper(trim((string) ($t['format'] ?? '')));
        if ($s !== '') {
            return $s;
        }
        $ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        return $ext !== '' ? $ext : '?';
    }

    private function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }
}
