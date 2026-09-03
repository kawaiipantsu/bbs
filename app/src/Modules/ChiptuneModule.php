<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;

/**
 * CHIPTUNE RADIO - a station of real tracker modules (.xm / .s3m / .mod)
 * streamed on the client through libopenmpt (WASM). The module itself only
 * renders the dial and emits `meta.chiptune` commands; the browser
 * (html/js/chiptune.js, wired in app.js) does the actual playback and
 * auto-advances from track to track.
 *
 * State ($st):
 *   sel     - highlighted row (0-based)
 *   playing - index of the track we last told the client to play, or null
 */
final class ChiptuneModule extends Module
{
    private const MANIFEST = '/html/media/tracks/manifest.json';

    public static function slugs(): array
    {
        return ['chiptune'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $tracks = $this->tracks();
        $count  = count($tracks);

        $key = (string) ($in['key'] ?? '');
        $sel = (int) ($st['sel'] ?? 0);
        if ($sel < 0 || $sel >= max(1, $count)) {
            $sel = 0;
        }
        $playing = array_key_exists('playing', $st) && $st['playing'] !== null
            ? (int) $st['playing']
            : null;
        if ($playing !== null && ($playing < 0 || $playing >= $count)) {
            $playing = null;
        }

        // ---- input -----------------------------------------------------
        $cmd = null;   // 'play' | 'stop' | 'next'

        if ($key === "\x1b" || $key === 'Q' || $key === 'ESC') {
            return $e->exitModule();
        }
        if ($count > 0) {
            if (ctype_digit($key) && $key !== '0') {
                $d = (int) $key - 1;
                if ($d < $count) {
                    $sel = $d;
                }
            } elseif ($key === ' ' || $key === 'SPACE' || $key === 'DOWN') {
                $sel = ($sel + 1) % $count;
            } elseif ($key === 'UP') {
                $sel = ($sel - 1 + $count) % $count;
            } elseif ($key === "\r" || $key === 'ENTER') {
                $playing = $sel;
                $cmd = 'play';
            } elseif ($key === 'N') {
                $playing = $playing === null ? $sel : ($playing + 1) % $count;
                $sel = $playing;
                $cmd = 'next';
            } elseif ($key === 'S') {
                $playing = null;
                $cmd = 'stop';
            }
        }

        $st['sel']     = $sel;
        $st['playing'] = $playing;

        // ---- render --------------------------------------------------
        $f = Frame::make('screen')
            ->title('Chiptune Radio')
            ->mode('menu')
            ->header('CHIPTUNE RADIO', $count . ' modules on rotation')
            ->blank();

        if ($count === 0) {
            $f->pipe('|12   No modules found.')
              ->pipe('|08   Expected a manifest at html/media/tracks/manifest.json');
            return $f->footer('Q / ESC  back');
        }

        $f->pipe('|08   Tracker music from The Mod Archive, streamed through libopenmpt (WASM).')
          ->blank();

        // NOW PLAYING panel - reflects the last command only; the board cannot
        // see real client-side playback state.
        if ($playing !== null && isset($tracks[$playing])) {
            $t = $tracks[$playing];
            $f->pipe('|19|00 NOW PLAYING |CL |15' . $this->titleOf($t)
                . ($this->artistOf($t) !== '' ? ' |08by |07' . $this->artistOf($t) : '')
                . ' |08[' . $this->fmtOf($t) . ']');
            $f->pipe('|08   station auto-advances · press |15S|08 to stop and return to the background music');
        } else {
            $f->pipe('|18|00 IDLE |CL |08nothing on the wire · background music is playing');
            $f->pipe('|08   pick a module and press |15ENTER|08 to put it on air');
        }
        $f->blank()->pipe('|08   ' . str_repeat('-', 92))->blank();

        foreach ($tracks as $i => $t) {
            $n       = $i + 1;
            $marker  = $i === $sel ? '|10>' : '|08 ';
            $onair   = $i === $playing ? '  |10<< ON AIR' : '';
            $numCol  = $i === $sel ? '|15' : '|07';
            $ttlCol  = $i === $sel ? '|15' : '|07';
            $artist  = $this->artistOf($t);
            $title   = str_pad($this->clip($this->titleOf($t), 30), 30);
            $artStr  = $artist !== ''
                ? '|09' . str_pad($this->clip($artist, 18), 18)
                : '|08' . str_pad('-', 18);
            $fmt     = str_pad($this->fmtOf($t), 4);
            $f->pipe(sprintf(
                '  %s |08[%s%2d|08] %s%s  %s |08%s%s',
                $marker, $numCol, $n, $ttlCol, $title, $artStr, $fmt, $onair
            ));
        }

        $f->blank();
        $f->pipe('|08   digits / SPACE select   ENTER play   N next   S stop   Q back');

        // The client can't fetch /media/tracks/manifest.json (static *.json is
        // denied by .htaccess), so hand it the catalogue in the frame instead.
        $f->meta(['chiptuneCatalog' => array_map(
            fn (array $t): array => [
                'file'   => (string) ($t['file'] ?? ''),
                'title'  => $this->titleOf($t),
                'artist' => $this->artistOf($t),
                'format' => $this->fmtOf($t),
            ],
            $tracks
        )]);

        if ($cmd === 'play' && isset($tracks[$playing])) {
            $t = $tracks[$playing];
            $f->meta(['chiptune' => [
                'action' => 'play',
                'index'  => $playing,
                'title'  => $this->titleOf($t),
                'artist' => $this->artistOf($t),
                'format' => $this->fmtOf($t),
            ]]);
        } elseif ($cmd === 'next') {
            $f->meta(['chiptune' => ['action' => 'next', 'index' => $playing]]);
        } elseif ($cmd === 'stop') {
            $f->meta(['chiptune' => ['action' => 'stop']]);
        }

        return $f->footer('digits/SPACE select · ENTER play · N next · S stop · Q back');
    }

    // -----------------------------------------------------------------

    /** @return list<array<string,string>> */
    private function tracks(): array
    {
        $path = dirname(__DIR__, 3) . self::MANIFEST;
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['tracks']) || !is_array($data['tracks'])) {
            return [];
        }
        $out = [];
        foreach ($data['tracks'] as $t) {
            if (is_array($t) && isset($t['file'])) {
                $out[] = $t;
            }
        }
        return array_values($out);
    }

    /** @param array<string,mixed> $t */
    private function titleOf(array $t): string
    {
        $s = trim((string) ($t['title'] ?? ''));
        return $s !== '' ? $s : (string) ($t['file'] ?? 'unknown');
    }

    /** @param array<string,mixed> $t */
    private function artistOf(array $t): string
    {
        return trim((string) ($t['artist'] ?? ''));
    }

    /** @param array<string,mixed> $t */
    private function fmtOf(array $t): string
    {
        $s = strtoupper(trim((string) ($t['format'] ?? '')));
        if ($s !== '') {
            return $s;
        }
        $ext = strtoupper(pathinfo((string) ($t['file'] ?? ''), PATHINFO_EXTENSION));
        return $ext !== '' ? $ext : '?';
    }

    private function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }
}
