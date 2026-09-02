<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Core\Config;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Core\Response;
use Bbs\Core\Storage;

/**
 * robots.txt, sitemap.xml, manifest, security.txt and GD-generated OpenGraph
 * share images in the board's CRT identity style.
 */
final class SeoController
{
    private function origin(): string
    {
        return rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/');
    }

    public function robots(Request $req): Response
    {
        $o = $this->origin();
        $body = "User-agent: *\nAllow: /$\nAllow: /b/\nAllow: /m/\nAllow: /u/\nAllow: /news/\nAllow: /g/\n"
              . "Disallow: /api/\nDisallow: /admin\n\nSitemap: $o/sitemap.xml\n";
        return Response::text($body)->withHeader('Cache-Control', 'public, max-age=86400');
    }

    public function sitemap(Request $req): Response
    {
        $o = $this->origin();
        $urls = [['loc' => $o . '/', 'pri' => '1.0', 'freq' => 'hourly']];

        foreach (Db::all('SELECT slug, last_post_at FROM boards ORDER BY sort') as $b) {
            $urls[] = ['loc' => $o . '/b/' . rawurlencode($b['slug']), 'pri' => '0.7', 'freq' => 'daily',
                       'lastmod' => $b['last_post_at'] ? date('c', strtotime($b['last_post_at'])) : null];
        }
        foreach (['it', 'hacking', 'tech', 'entertainment'] as $c) {
            $urls[] = ['loc' => $o . '/news/' . $c, 'pri' => '0.6', 'freq' => 'hourly'];
        }
        foreach (Db::all('SELECT slug FROM games WHERE enabled = 1') as $g) {
            $urls[] = ['loc' => $o . '/g/' . rawurlencode($g['slug']), 'pri' => '0.5', 'freq' => 'weekly'];
        }
        foreach (Db::all(
            "SELECT id, GREATEST(created_at, COALESCE(edited_at, created_at)) AS lm
             FROM messages WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 2000"
        ) as $m) {
            $urls[] = ['loc' => $o . '/m/' . $m['id'], 'pri' => '0.4', 'freq' => 'monthly',
                       'lastmod' => date('c', strtotime($m['lm']))];
        }
        foreach (Db::all(
            "SELECT handle, updated_at FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY id LIMIT 2000"
        ) as $u) {
            $urls[] = ['loc' => $o . '/u/' . rawurlencode($u['handle']), 'pri' => '0.3', 'freq' => 'weekly',
                       'lastmod' => date('c', strtotime($u['updated_at']))];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>'
                 . ($u['lastmod'] ?? null ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '')
                 . '<changefreq>' . $u['freq'] . '</changefreq>'
                 . '<priority>' . $u['pri'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        return Response::raw($xml, 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function manifest(Request $req): Response
    {
        $name = Config::setting('site_name', 'THUGS(red) BBS');
        $m = [
            'name'             => $name,
            'short_name'       => 'THUGS BBS',
            'description'      => Config::setting('seo_description', ''),
            'start_url'        => '/',
            'scope'           => '/',
            'display'          => 'fullscreen',
            'orientation'     => 'landscape',
            'background_color' => '#0e1013',
            'theme_color'      => '#0e1013',
            'icons' => [
                ['src' => '/media/images/favicon-180.png', 'sizes' => '180x180', 'type' => 'image/png'],
                ['src' => '/media/images/favicon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/media/images/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ];
        return Response::raw(json_encode($m, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 'application/manifest+json')
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    }

    public function securityTxt(Request $req): Response
    {
        $o = $this->origin();
        $exp = date('c', strtotime('+1 year'));
        $body = "Contact: mailto:sysop@thugs.red\nExpires: $exp\nPreferred-Languages: en\n"
              . "Canonical: $o/.well-known/security.txt\nPolicy: $o/b/feedback\n";
        return Response::text($body)->withHeader('Cache-Control', 'public, max-age=86400');
    }

    /** GET /og/{slug}.png - cached GD share card. */
    public function og(Request $req, array $args): Response
    {
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $args['slug']) ?: 'default';
        $cacheFile = Storage::cachePath('og/' . $slug . '.png');
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 3600) {
            return $this->imageResponse((string) file_get_contents($cacheFile));
        }

        [$headline, $sub] = $this->ogText($slug);
        $png = $this->drawCard($headline, $sub);
        @mkdir(dirname($cacheFile), 0770, true);
        @file_put_contents($cacheFile, $png);

        return $this->imageResponse($png);
    }

    /** Share images must be loadable cross-origin so link unfurlers can render them. */
    private function imageResponse(string $png): Response
    {
        return Response::raw($png, 'image/png')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->withHeader('Timing-Allow-Origin', '*')
            ->withHeader('Content-Length', (string) strlen($png))
            ->withHeader('Vary', 'Accept');
    }

    /** @return array{0:string,1:string} */
    private function ogText(string $slug): array
    {
        $site = Config::setting('site_name', 'THUGS(red) BBS');
        if ($slug === 'default') {
            return [$site, Config::setting('site_tagline', 'ANSI since the phone lines were warm')];
        }
        if (str_starts_with($slug, 'msg-')) {
            $m = Db::one('SELECT subject, from_handle FROM messages WHERE id = ?', [(int) substr($slug, 4)]);
            return $m ? [$m['subject'], 'posted by ' . $m['from_handle'] . ' on ' . $site] : [$site, ''];
        }
        if (str_starts_with($slug, 'board-')) {
            $b = Db::one('SELECT name, description FROM boards WHERE slug = ?', [substr($slug, 6)]);
            return $b ? [$b['name'], $b['description'] ?: $site] : [$site, ''];
        }
        if (str_starts_with($slug, 'user-')) {
            $h = substr($slug, 5);
            $u = Db::one('SELECT handle, tagline FROM users WHERE handle = ?', [$h]);
            return $u ? [$u['handle'] . ' @ ' . $site, $u['tagline'] ?: 'BBS caller'] : [$site, ''];
        }
        if (str_starts_with($slug, 'news-')) {
            $c = substr($slug, 5);
            return [strtoupper($c) . ' NEWS WIRE', 'live headlines on ' . $site];
        }
        if (str_starts_with($slug, 'game-')) {
            $g = Db::one('SELECT name, description FROM games WHERE slug = ?', [substr($slug, 5)]);
            return $g ? [$g['name'], $g['description']] : [$site, ''];
        }
        return [$site, Config::setting('site_tagline', '')];
    }

    private function drawCard(string $headline, string $sub): string
    {
        $W = 1200;
        $H = 630;
        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        $ca = static fn ($r, $g, $b, $a = 0) => imagecolorallocatealpha($im, $r, $g, $b, $a);
        $line  = $ca(0x27, 0x2c, 0x36);
        $red   = $ca(0xe2, 0x22, 0x3b);
        $text  = $ca(0xe6, 0xe9, 0xef);
        $muted = $ca(0x97, 0xa0, 0xb0);
        $green = $ca(0x5b, 0xe6, 0xa3);
        imagefilledrectangle($im, 0, 0, $W, $H, $ca(0x05, 0x07, 0x0a));

        // CRT-monitor photo backdrop: cover-fit, darkened, then a left gradient
        $monPath = BBS_ROOT . '/html/media/images/monitor.png';
        $mon = is_file($monPath) ? @imagecreatefrompng($monPath) : false;
        if ($mon) {
            $mw = imagesx($mon);
            $mh = imagesy($mon);
            $s  = max($W / $mw, $H / $mh) * 1.06;
            $dw = (int) ($mw * $s);
            $dh = (int) ($mh * $s);
            $tmp = imagecreatetruecolor($W, $H);
            imagecopyresampled($tmp, $mon, 0, 0, 0, 0, $W, $H, $mw, $mh);
            imagecopyresampled($tmp, $mon, (int) (($W - $dw) / 2), (int) (($H - $dh) / 2) - 38, 0, 0, $dw, $dh, $mw, $mh);
            imagecopymerge($im, $tmp, 0, 0, 0, 0, $W, $H, 60);
            imagedestroy($tmp);
            imagedestroy($mon);
        }
        for ($x = 0; $x < $W; $x++) {
            imagefilledrectangle($im, $x, 0, $x, $H, $ca(0, 0, 0, max(10, (int) (110 - 96 * $x / $W))));
        }
        for ($y = 0; $y < $H; $y += 3) {
            imageline($im, 0, $y, $W, $y, $ca(0, 0, 0, 96));
        }
        imagefilledrectangle($im, 0, 0, $W, 10, $red);
        imagesetthickness($im, 2);
        imagerectangle($im, 24, 24, $W - 25, $H - 25, $ca(0x27, 0x2c, 0x36, 50));
        imagesetthickness($im, 1);

        $font = $this->font();
        if ($font) {
            imagettftext($im, 15, 0, 64, 110, $muted, $font, '$ telnet ' . Config::setting('telnet_host', 'bbs.thugs.red'));
            $this->wrapTtf($im, $font, 44, 64, 232, $W - 130, $text, $headline, 58);
            $this->wrapTtf($im, $font, 19, 64, $H - 116, $W - 130, $muted, $sub, 30);
            imagettftext($im, 15, 0, 64, $H - 44, $red, $font, strtoupper(Config::setting('site_name', 'THUGS(red) BBS')));
        } else {
            imagestring($im, 5, 64, 232, substr($headline, 0, 60), $text);
        }

        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();
        imagedestroy($im);
        return $data;
    }

    private function font(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    private function wrapTtf($im, string $font, float $size, int $x, int $y, int $maxX, int $color, string $s, int $lh): void
    {
        $words = explode(' ', $s);
        $lineStr = '';
        foreach ($words as $w) {
            $try = $lineStr === '' ? $w : "$lineStr $w";
            $bb = imagettfbbox($size, 0, $font, $try);
            if ($bb[2] > $maxX - $x && $lineStr !== '') {
                imagettftext($im, $size, 0, $x, $y, $color, $font, $lineStr);
                $y += $lh;
                $lineStr = $w;
            } else {
                $lineStr = $try;
            }
        }
        if ($lineStr !== '') {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $lineStr);
        }
    }
}
