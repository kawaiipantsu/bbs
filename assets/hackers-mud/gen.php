<?php

/**
 * Generates the static image assets for the /hackers-mud client that need to
 * be real files (favicons). The OG / banner cards are drawn on the fly by
 * HackersMudController. Run:  php assets/hackers-mud/gen.php
 *
 * All output is original procedural pixel art - released CC0.
 */

declare(strict_types=1);

$out = dirname(__DIR__, 2) . '/html/hackers-mud/assets';
@mkdir($out, 0775, true);

function icon(int $size): \GdImage
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true);
    $bg   = imagecolorallocate($im, 10, 10, 18);
    $red  = imagecolorallocate($im, 255, 45, 85);
    $cyan = imagecolorallocate($im, 102, 224, 255);
    $ink  = imagecolorallocate($im, 240, 244, 255);
    imagefilledrectangle($im, 0, 0, $size, $size, $bg);

    // neon frame
    $m = max(1, (int) round($size * 0.06));
    imagesetthickness($im, max(1, (int) round($size * 0.05)));
    imagerectangle($im, $m, $m, $size - $m - 1, $size - $m - 1, $red);

    // pixel "H"
    $u = $size / 16;
    $col = $cyan;
    $bar = static function ($x, $y, $w, $h) use ($im, $u, $col) {
        imagefilledrectangle($im, (int) ($x * $u), (int) ($y * $u), (int) (($x + $w) * $u), (int) (($y + $h) * $u), $col);
    };
    $bar(4, 3.5, 2, 9);   // left leg
    $bar(10, 3.5, 2, 9);  // right leg
    $bar(4, 7, 8, 2);     // crossbar
    // glow dot
    imagefilledellipse($im, (int) (12.5 * $u), (int) (4 * $u), (int) ($u * 2), (int) ($u * 2), $ink);

    return $im;
}

foreach ([32, 192] as $sz) {
    $im = icon($sz);
    $f = "$out/favicon-$sz.png";
    imagepng($im, $f);
    imagedestroy($im);
    echo "wrote $f\n";
}
echo "done.\n";
