<?php

declare(strict_types=1);

namespace Bbs\Bbs;

/**
 * Renders BBS screen bodies into styled "runs" the JS terminal draws.
 *
 * Supported input formats (screens.content_type):
 *   pipe   - |00..|15 set foreground, |16..|23 set background,
 *            |CL clear attrs, |CR carriage return helper (ignored).
 *   ansi   - a subset of ECMA-48 SGR: ESC[...m  (0/1/5/7/22/25/27,
 *            30-37, 40-47, 90-97, 100-107, 38;5;n, 48;5;n). Cursor
 *            sequences are stripped.
 *   plain  - no markup, everything is fg 7 / bg 0.
 *
 * Template tokens {{name}} are substituted from $ctx before parsing; token
 * values have '|' and ESC stripped so data can't inject colour.
 *
 * Output: list<list<array{s:string,f:int,b:int,o:bool,k:bool}>>
 *   f = foreground 0-255, b = background 0-15, o = bold/bright, k = blink
 */
final class AnsiRenderer
{
    private const DEF_FG = 7;
    private const DEF_BG = 0;

    /**
     * @param array<string,scalar|null> $ctx
     * @return list<list<array{s:string,f:int,b:int,o:bool,k:bool}>>
     */
    public static function render(string $body, string $type = 'pipe', array $ctx = []): array
    {
        $body = self::applyTokens($body, $ctx);
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        return match ($type) {
            'ansi'  => self::parseAnsi($body),
            'plain' => self::parsePlain($body),
            default => self::parsePipe($body),
        };
    }

    /** Flatten runs back to a plain string (used for OG images, search, length calc). */
    public static function plainText(string $body, string $type = 'pipe', array $ctx = []): string
    {
        $lines = self::render($body, $type, $ctx);
        $out = [];
        foreach ($lines as $line) {
            $s = '';
            foreach ($line as $run) {
                $s .= $run['s'];
            }
            $out[] = rtrim($s);
        }
        return implode("\n", $out);
    }

    // -----------------------------------------------------------------
    private static function applyTokens(string $body, array $ctx): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($ctx) {
            $v = $ctx[strtolower($m[1])] ?? '';
            $v = (string) $v;
            return str_replace(['|', "\x1b"], ['', ''], $v);
        }, $body) ?? $body;
    }

    /** @return list<list<array{s:string,f:int,b:int,o:bool,k:bool}>> */
    private static function parsePlain(string $body): array
    {
        $out = [];
        foreach (explode("\n", $body) as $line) {
            $out[] = $line === '' ? [] : [self::run($line, self::DEF_FG, self::DEF_BG, false, false)];
        }
        return $out;
    }

    /** @return list<list<array{s:string,f:int,b:int,o:bool,k:bool}>> */
    private static function parsePipe(string $body): array
    {
        $out = [];
        foreach (explode("\n", $body) as $line) {
            $fg = self::DEF_FG;
            $bg = self::DEF_BG;
            $runs = [];
            $buf = '';
            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $ch = $line[$i];
                if ($ch === '|' && $i + 2 < $len) {
                    $code = substr($line, $i + 1, 2);
                    if (ctype_digit($code)) {
                        if ($buf !== '') {
                            $runs[] = self::run($buf, $fg, $bg, $fg >= 8, false);
                            $buf = '';
                        }
                        $n = (int) $code;
                        if ($n <= 15) {
                            $fg = $n;
                        } elseif ($n <= 23) {
                            $bg = $n - 16;
                        }
                        $i += 2;
                        continue;
                    }
                    if ($code === 'CL' || $code === 'cl') {
                        if ($buf !== '') {
                            $runs[] = self::run($buf, $fg, $bg, $fg >= 8, false);
                            $buf = '';
                        }
                        $fg = self::DEF_FG;
                        $bg = self::DEF_BG;
                        $i += 2;
                        continue;
                    }
                }
                // multibyte-safe: copy whole UTF-8 sequence
                $ord = ord($ch);
                if ($ord >= 0xF0) {
                    $buf .= substr($line, $i, 4); $i += 3;
                } elseif ($ord >= 0xE0) {
                    $buf .= substr($line, $i, 3); $i += 2;
                } elseif ($ord >= 0xC0) {
                    $buf .= substr($line, $i, 2); $i += 1;
                } else {
                    $buf .= $ch;
                }
            }
            if ($buf !== '') {
                $runs[] = self::run($buf, $fg, $bg, $fg >= 8, false);
            }
            $out[] = $runs;
        }
        return $out;
    }

    /** @return list<list<array{s:string,f:int,b:int,o:bool,k:bool}>> */
    private static function parseAnsi(string $body): array
    {
        // strip non-SGR CSI sequences (cursor moves, clears, etc.)
        $body = preg_replace('/\x1b\[[0-9;]*[A-HJKfhlsu]/', '', $body) ?? $body;

        $out = [];
        $fg = self::DEF_FG; $bg = self::DEF_BG; $bold = false; $blink = false;
        foreach (explode("\n", $body) as $line) {
            $runs = [];
            $buf = '';
            $offset = 0;
            if (preg_match_all('/\x1b\[([0-9;]*)m/', $line, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $k => $hit) {
                    $pre = substr($line, $offset, $hit[1] - $offset);
                    if ($pre !== '') {
                        $buf .= $pre;
                    }
                    if ($buf !== '') {
                        $runs[] = self::run($buf, $bold && $fg < 8 ? $fg + 8 : $fg, $bg, $bold, $blink);
                        $buf = '';
                    }
                    [$fg, $bg, $bold, $blink] = self::applySgr($m[1][$k][0], $fg, $bg, $bold, $blink);
                    $offset = $hit[1] + strlen($hit[0]);
                }
            }
            $tail = substr($line, $offset);
            if ($tail !== '') {
                $runs[] = self::run($tail, $bold && $fg < 8 ? $fg + 8 : $fg, $bg, $bold, $blink);
            }
            $out[] = $runs;
        }
        return $out;
    }

    private static function applySgr(string $params, int $fg, int $bg, bool $bold, bool $blink): array
    {
        $parts = $params === '' ? [0] : array_map('intval', explode(';', $params));
        for ($i = 0; $i < count($parts); $i++) {
            $p = $parts[$i];
            match (true) {
                $p === 0            => [$fg, $bg, $bold, $blink] = [7, 0, false, false],
                $p === 1            => $bold = true,
                $p === 22           => $bold = false,
                $p === 5, $p === 6  => $blink = true,
                $p === 25           => $blink = false,
                $p === 7            => [$fg, $bg] = [$bg, $fg],
                $p >= 30 && $p <= 37   => $fg = $p - 30,
                $p >= 40 && $p <= 47   => $bg = $p - 40,
                $p >= 90 && $p <= 97   => $fg = $p - 90 + 8,
                $p >= 100 && $p <= 107 => $bg = $p - 100 + 8,
                $p === 38 && ($parts[$i + 1] ?? null) === 5 => [$fg, $i] = [$parts[$i + 2] ?? 7, $i + 2],
                $p === 48 && ($parts[$i + 1] ?? null) === 5 => [$bg, $i] = [$parts[$i + 2] ?? 0, $i + 2],
                default => null,
            };
        }
        return [$fg, $bg, $bold, $blink];
    }

    /** @return array{s:string,f:int,b:int,o:bool,k:bool} */
    private static function run(string $s, int $fg, int $bg, bool $bold, bool $blink): array
    {
        return ['s' => $s, 'f' => max(0, min(255, $fg)), 'b' => max(0, min(15, $bg)), 'o' => $bold, 'k' => $blink];
    }
}
