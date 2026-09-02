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
            ->withHeader('Cache-Control', 'public, max-age=86400');
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
            return Response::raw((string) file_get_contents($cacheFile), 'image/png')
                ->withHeader('Cache-Control', 'public, max-age=3600');
        }

        [$headline, $sub] = $this->ogText($slug);
        $png = $this->drawCard($headline, $sub);
        @mkdir(dirname($cacheFile), 0770, true);
        @file_put_contents($cacheFile, $png);

        return Response::raw($png, 'image/png')->withHeader('Cache-Control', 'public, max-age=3600');
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
        $bg     = imagecolorallocate($im, 0x0e, 0x10, 0x13);
        $panel  = imagecolorallocate($im, 0x16, 0x19, 0x1f);
        $line   = imagecolorallocate($im, 0x27, 0x2c, 0x36);
        $red    = imagecolorallocate($im, 0xe2, 0x22, 0x3b);
        $text   = imagecolorallocate($im, 0xe6, 0xe9, 0xef);
        $muted  = imagecolorallocate($im, 0x97, 0xa0, 0xb0);
        imagefill($im, 0, 0, $bg);

        // scanline texture
        for ($y = 0; $y < $H; $y += 3) {
            imageline($im, 0, $y, $W, $y, $panel);
        }
        // border frame
        imagerectangle($im, 24, 24, $W - 25, $H - 25, $line);
        imagerectangle($im, 26, 26, $W - 27, $H - 27, $line);
        imagefilledrectangle($im, 24, 24, $W - 25, 34, $red);

        $font = dirname(__DIR__, 3) . '/html/media/fonts/PxPlus_IBM_VGA_9x16.ttf';
        $useTtf = is_file($font);

        if ($useTtf) {
            imagettftext($im, 15, 0, 60, 110, $muted, $font, '$ telnet ' . Config::setting('telnet_host', 'bbs.thugs.red'));
            $this->wrapTtf($im, $font, 46, 60, 240, $W - 120, $text, $headline, 58);
            $this->wrapTtf($im, $font, 20, 60, $H - 120, $W - 120, $muted, $sub, 30);
            imagettftext($im, 16, 0, 60, $H - 50, $red, $font, Config::setting('site_name', 'THUGS(red) BBS'));
        } else {
            imagestring($im, 5, 60, 90, '$ telnet ' . Config::setting('telnet_host', 'bbs.thugs.red'), $muted);
            imagestring($im, 5, 60, 240, substr($headline, 0, 60), $text);
            imagestring($im, 4, 60, 300, substr($sub, 0, 90), $muted);
            imagestring($im, 5, 60, $H - 70, Config::setting('site_name', 'THUGS(red) BBS'), $red);
        }

        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();
        imagedestroy($im);
        return $data;
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
