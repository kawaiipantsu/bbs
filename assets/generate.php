<?php
/**
 * THUGS(red) BBS - identity asset generator.
 *
 *   php assets/generate.php
 *
 * Renders the favicon set, social/OpenGraph cards, a default avatar and a
 * desktop wallpaper from the palette + CRT identity, then copies the web-facing
 * ones into html/media/images/. Re-run any time the palette changes.
 */

declare(strict_types=1);

$ROOT = dirname(__DIR__);
$DIST = $ROOT . '/assets/dist';
$WEB  = $ROOT . '/html/media/images';
@mkdir($DIST, 0775, true);
@mkdir($WEB, 0775, true);

const BG     = [0x0e, 0x10, 0x13];
const SURF   = [0x16, 0x19, 0x1f];
const LINE   = [0x27, 0x2c, 0x36];
const TEXT   = [0xe6, 0xe9, 0xef];
const MUTED  = [0x97, 0xa0, 0xb0];
const RED    = [0xe2, 0x22, 0x3b];
const GREEN  = [0x5b, 0xe6, 0xa3];
const SCREEN = [0x05, 0x07, 0x0a];

$FONT  = null;
foreach ([
    '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
] as $f) {
    if (is_file($f)) { $FONT = $f; break; }
}

function c($im, array $rgb) { return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]); }
function ca($im, array $rgb, int $a) { return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a); }

function scanlines($im, int $w, int $h, int $step = 3, int $alpha = 100): void
{
    $col = imagecolorallocatealpha($im, 0, 0, 0, $alpha);
    for ($y = 0; $y < $h; $y += $step) {
        imageline($im, 0, $y, $w, $y, $col);
    }
}

/** The monitor glyph, scaled to a $s x $s box at ($x,$y). */
function monitor($im, int $x, int $y, int $s): void
{
    $body   = c($im, SURF);
    $edge   = c($im, LINE);
    $scr    = c($im, SCREEN);
    $red    = c($im, RED);
    $green  = c($im, GREEN);
    $muted  = c($im, MUTED);
    $u = fn ($v) => (int) round($v * $s);

    imagefilledrectangle($im, $x + $u(0.06), $y + $u(0.10), $x + $u(0.94), $y + $u(0.74), $body);
    imagerectangle($im, $x + $u(0.06), $y + $u(0.10), $x + $u(0.94), $y + $u(0.74), $edge);
    imagefilledrectangle($im, $x + $u(0.12), $y + $u(0.16), $x + $u(0.88), $y + $u(0.64), $scr);
    for ($i = 0; $i < 6; $i++) {
        $ly = $y + $u(0.20 + $i * 0.072);
        imageline($im, $x + $u(0.12), $ly, $x + $u(0.88), $ly, $edge);
    }
    imagefilledrectangle($im, $x + $u(0.20), $y + $u(0.30), $x + $u(0.27), $y + $u(0.44), $red);
    imagefilledrectangle($im, $x + $u(0.31), $y + $u(0.36), $x + $u(0.56), $y + $u(0.40), $green);
    imagefilledrectangle($im, $x + $u(0.31), $y + $u(0.24), $x + $u(0.48), $y + $u(0.27), $muted);
    imagefilledellipse($im, $x + $u(0.80), $y + $u(0.80), $u(0.05), $u(0.05), $red);
    imagefilledrectangle($im, $x + $u(0.40), $y + $u(0.74), $x + $u(0.60), $y + $u(0.82), $body);
    imagefilledrectangle($im, $x + $u(0.30), $y + $u(0.82), $x + $u(0.70), $y + $u(0.86), $edge);
}

function save($im, string $path): void
{
    imagesavealpha($im, true);
    imagepng($im, $path);
    imagedestroy($im);
    echo "  " . basename($path) . "\n";
}

// ---- favicons -----------------------------------------------------------
echo "favicons:\n";
foreach ([16, 32, 180, 512] as $sz) {
    $im = imagecreatetruecolor($sz, $sz);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, ca($im, BG, 0));
    $r = (int) max(2, $sz * 0.18);
    imagefilledrectangle($im, 0, 0, $sz - 1, $sz - 1, c($im, BG));
    monitor($im, (int) ($sz * 0.02), (int) ($sz * 0.04), (int) ($sz * 0.96));
    save($im, "$DIST/favicon-$sz.png");
    copy("$DIST/favicon-$sz.png", "$WEB/favicon-$sz.png");
}
copy(__DIR__ . '/favicon.svg', "$WEB/favicon.svg");
echo "  favicon.svg -> web\n";

// ---- social card factory ---------------------------------------------
function card(int $W, int $H, string $out, ?string $font): void
{
    $im = imagecreatetruecolor($W, $H);
    imagefill($im, 0, 0, c($im, BG));
    // panel
    imagefilledrectangle($im, 0, 0, $W, $H, c($im, BG));
    for ($y = 0; $y < $H; $y += 3) {
        imageline($im, 0, $y, $W, $y, c($im, SURF));
    }
    imagefilledrectangle($im, 0, 0, $W, (int) ($H * 0.018), c($im, RED));
    imagerectangle($im, 22, 22, $W - 23, $H - 23, c($im, LINE));

    $mSize = (int) ($H * 0.42);
    monitor($im, $W - $mSize - 60, (int) (($H - $mSize) / 2), $mSize);

    if ($font) {
        imagettftext($im, max(12, $H * 0.03), 0, 56, (int) ($H * 0.20), c($im, MUTED), $font, '$ telnet bbs.thugs.red');
        imagettftext($im, max(28, $H * 0.11), 0, 52, (int) ($H * 0.52), c($im, TEXT), $font, 'THUGS(red) BBS');
        imagettftext($im, max(14, $H * 0.035), 0, 56, (int) ($H * 0.66), c($im, MUTED), $font, 'A keyboard-driven ANSI bulletin board, inside a CRT.');
        imagettftext($im, max(14, $H * 0.03), 0, 56, (int) ($H * 0.88), c($im, RED), $font, 'BULLETIN  BOARD  SYSTEM');
    } else {
        imagestring($im, 5, 56, (int) ($H * 0.2), 'THUGS(red) BBS', c($im, TEXT));
    }
    imagepng($im, $out);
    imagedestroy($im);
    echo "  " . basename($out) . "\n";
}

echo "social:\n";
card(1200, 630, "$DIST/og-default.png", $FONT);
copy("$DIST/og-default.png", "$WEB/og-default.png");
card(1280, 640, "$DIST/github-social-1280x640.png", $FONT);
card(1500, 500, "$DIST/banner-1500x500.png", $FONT);

// ---- default avatar -------------------------------------------------
echo "avatar:\n";
$im = imagecreatetruecolor(256, 256);
imagefill($im, 0, 0, c($im, BG));
monitor($im, 12, 22, 232);
scanlines($im, 256, 256, 3, 110);
save($im, "$DIST/avatar-default.png");
copy("$DIST/avatar-default.png", "$WEB/avatar-default.png");

// ---- wallpaper ----------------------------------------------------
echo "wallpaper:\n";
$W = 2560; $H = 1440;
$im = imagecreatetruecolor($W, $H);
imagefill($im, 0, 0, c($im, SCREEN));
for ($y = 0; $y < $H; $y += 4) {
    imageline($im, 0, $y, $W, $y, c($im, BG));
}
$ms = 900;
monitor($im, (int) (($W - $ms) / 2), (int) (($H - $ms) / 2) - 40, $ms);
// vignette
for ($i = 0; $i < 220; $i++) {
    $a = (int) (127 - $i / 2);
    if ($a < 0) break;
    imagerectangle($im, $i, $i, $W - 1 - $i, $H - 1 - $i, imagecolorallocatealpha($im, 0, 0, 0, max(80, $a)));
}
if ($FONT) {
    imagettftext($im, 22, 0, 60, $H - 60, c($im, MUTED), $FONT, 'CONNECT 57600/ARQ/V90/LAPM   -   thugs.red');
}
save($im, "$DIST/wallpaper-2560x1440.png");

echo "\nDone. Web assets updated in html/media/images/.\n";
