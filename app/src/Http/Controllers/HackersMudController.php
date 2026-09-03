<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Core\Config;
use Bbs\Core\Request;
use Bbs\Core\Response;

/**
 * The standalone graphical Hackers-MUD client at /hackers-mud - HTML shell +
 * procedurally drawn social / OG images. All game assets and logic are static
 * files under html/hackers-mud/ ; this only serves the entry document and the
 * share graphics.
 */
final class HackersMudController
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function assetV(): string
    {
        $newest = 0;
        foreach (['/html/hackers-mud/js', '/html/hackers-mud/css'] as $d) {
            foreach (glob($this->root() . $d . '/*.{js,css}', GLOB_BRACE) ?: [] as $f) {
                $newest = max($newest, (int) @filemtime($f));
            }
        }
        return substr((string) ($newest ?: time()), -6);
    }

    public function shell(Request $req): Response
    {
        $v      = $this->assetV();
        $origin = rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/');
        $url    = $origin . '/hackers-mud/';
        $title  = 'HACKERS-MUD :: Night City';
        $desc   = 'A cyberpunk MUD you play in the browser. Jack in, run the streets of Night City, '
                . 'fight, hack, level up. Uses your THUGS(red) BBS login.';
        $og     = $origin . '/hackers-mud/og.png';
        $e      = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);

        $html = <<<HTML
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
<title>{$e($title)}</title>
<meta name="description" content="{$e($desc)}">
<link rel="canonical" href="{$e($url)}">
<meta name="theme-color" content="#0a0a12">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Hackers-MUD">
<meta property="og:title" content="{$e($title)}">
<meta property="og:description" content="{$e($desc)}">
<meta property="og:url" content="{$e($url)}">
<meta property="og:image" content="{$e($og)}">
<meta property="og:image:secure_url" content="{$e($og)}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="HACKERS-MUD - a browser cyberpunk MUD">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$e($title)}">
<meta name="twitter:description" content="{$e($desc)}">
<meta name="twitter:image" content="{$e($og)}">
<link rel="icon" href="/hackers-mud/assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/hackers-mud/assets/favicon-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/hackers-mud/assets/favicon-192.png">
<link rel="stylesheet" href="/hackers-mud/css/game.css?v={$v}">
</head>
<body>
<noscript><div class="ns">HACKERS-MUD needs JavaScript. Turn it on and jack back in.</div></noscript>
<div id="app" data-v="{$v}"></div>
<script type="module" src="/hackers-mud/js/app.js?v={$v}"></script>
</body>
</html>
HTML;

        $csp = implode('; ', [
            "default-src 'self'", "base-uri 'self'", "form-action 'self'",
            "frame-ancestors 'none'", "img-src 'self' data: blob:",
            "media-src 'self' blob: data:", "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'", "script-src 'self'",
            "connect-src 'self'", "worker-src 'self' blob:",
        ]);
        return Response::html($html)
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Vary', 'Cookie');
    }

    /** The item catalogue / showcase at /hackers-mud/items */
    public function items(Request $req): Response
    {
        $v      = $this->assetV();
        $origin = rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/');
        $e      = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        $og     = $origin . '/hackers-mud/og.png';
        $html = <<<HTML
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HACKERS-MUD :: Item Database</title>
<meta name="description" content="Every item in Hackers-MUD - weapons, cyberware, gear, gadgets, food, loot - with icons and stats.">
<link rel="canonical" href="{$e($origin)}/hackers-mud/items">
<meta name="theme-color" content="#0a0a12">
<meta property="og:title" content="HACKERS-MUD - Item Database">
<meta property="og:description" content="Every item in the game, with icons and stats.">
<meta property="og:image" content="{$e($og)}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/hackers-mud/assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="stylesheet" href="/hackers-mud/css/game.css?v={$v}">
</head>
<body>
<div id="dex" data-v="{$v}"><div class="loading">loading the catalogue…</div></div>
<script type="module" src="/hackers-mud/js/items.js?v={$v}"></script>
</body>
</html>
HTML;
        $csp = implode('; ', [
            "default-src 'self'", "base-uri 'self'", "img-src 'self' data: blob:",
            "style-src 'self' 'unsafe-inline'", "script-src 'self'", "connect-src 'self'",
            "frame-ancestors 'none'",
        ]);
        return Response::html($html)
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /* ---- procedurally drawn share graphics ----------------------- */

    public function og(Request $req): Response
    {
        return $this->image($this->drawCard(1200, 630));
    }

    public function banner(Request $req): Response
    {
        return $this->image($this->drawCard(1500, 500));
    }

    private function image(string $png): Response
    {
        return Response::raw($png, 'image/png')
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->withHeader('Content-Length', (string) strlen($png));
    }

    private function drawCard(int $W, int $H): string
    {
        $im = imagecreatetruecolor($W, $H);
        $c = static fn ($r, $g, $b) => imagecolorallocate($im, $r, $g, $b);

        // vertical night-sky gradient
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            imageline($im, 0, $y, $W, $y, $c(
                (int) (8 + 26 * $t),
                (int) (6 + 8 * $t),
                (int) (18 + 34 * $t)
            ));
        }

        // perspective grid floor (lower third)
        $horizon = (int) ($H * 0.62);
        $grid = imagecolorallocatealpha($im, 240, 40, 90, 90);
        for ($i = -20; $i <= 20; $i++) {
            $x = $W / 2 + $i * ($W / 14);
            imageline($im, (int) ($W / 2 + $i * 26), $horizon, (int) $x, $H, $grid);
        }
        for ($gy = $horizon; $gy < $H; $gy += 6) {
            $step = ($gy - $horizon) / max(1, $H - $horizon);
            imageline($im, 0, $gy, $W, $gy, imagecolorallocatealpha($im, 240, 40, 90, (int) (110 - 70 * $step)));
        }

        // skyline silhouettes with lit windows
        mt_srand(1337);
        $bx = 0;
        while ($bx < $W) {
            $bw = mt_rand(46, 120);
            $bh = mt_rand((int) ($H * 0.18), (int) ($H * 0.52));
            $top = $horizon - $bh;
            imagefilledrectangle($im, $bx, $top, $bx + $bw, $horizon, $c(12, 10, 24));
            imagerectangle($im, $bx, $top, $bx + $bw, $horizon, imagecolorallocatealpha($im, 90, 200, 255, 100));
            for ($wy = $top + 8; $wy < $horizon - 6; $wy += 14) {
                for ($wx = $bx + 8; $wx < $bx + $bw - 6; $wx += 12) {
                    if (mt_rand(0, 3)) {
                        continue;
                    }
                    $lit = mt_rand(0, 1) ? $c(120, 230, 255) : $c(255, 180, 90);
                    imagefilledrectangle($im, $wx, $wy, $wx + 4, $wy + 6, $lit);
                }
            }
            $bx += $bw + mt_rand(6, 22);
        }
        mt_srand();

        // scanlines
        for ($y = 0; $y < $H; $y += 3) {
            imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, 0, 0, 0, 112));
        }

        $font = $this->font();
        $red  = $c(255, 45, 85);
        $cyan = $c(120, 240, 255);
        $white = $c(240, 244, 255);
        $mute = $c(150, 160, 190);

        if ($font) {
            $s = (int) ($H * 0.14);
            // glow
            for ($o = 6; $o >= 1; $o--) {
                imagettftext($im, $s, 0, 60 + $o, (int) ($H * 0.34) + $o, imagecolorallocatealpha($im, 255, 45, 85, 118), $font, 'HACKERS-MUD');
            }
            imagettftext($im, $s, 0, 60, (int) ($H * 0.34), $white, $font, 'HACKERS-MUD');
            imagettftext($im, (int) ($H * 0.045), 0, 64, (int) ($H * 0.45), $cyan, $font, 'a browser cyberpunk MUD  ::  NIGHT CITY');
            imagettftext($im, (int) ($H * 0.032), 0, 64, $H - 40, $mute, $font, 'thugs.red/hackers-mud   -   jack in with your BBS handle');
        } else {
            imagestring($im, 5, 60, (int) ($H * 0.3), 'HACKERS-MUD', $white);
        }

        // corner bracket accents
        foreach ([[30, 30], [$W - 30, 30], [30, $H - 30], [$W - 30, $H - 30]] as [$px, $py]) {
            $dx = $px < $W / 2 ? 22 : -22;
            $dy = $py < $H / 2 ? 22 : -22;
            imageline($im, $px, $py, $px + $dx, $py, $red);
            imageline($im, $px, $py, $px, $py + $dy, $red);
        }

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);
        return $png;
    }

    private function font(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }
}
