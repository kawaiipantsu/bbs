<?php
/**
 * THUGS(red) BBS - (re)install the graffiti logo screen from the ANSI art.
 *
 * Reads assets/THUGSred.ans, strips the cursor/screen-control escapes (keeps
 * the SGR colour), centres it for the configured terminal width, and stores
 * it as the `art.logo` screen. Then points the Main Menu header at it and
 * removes the old inline pixel banner from the Connected (MOTD) screen so the
 * new logo is not shown twice.
 *
 * Idempotent - safe to run again after editing assets/THUGSred.ans.
 *
 *   php contrib/install-logo.php            apply
 *   php contrib/install-logo.php --dry-run  show what it would do
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Bbs\Frame;
use Bbs\Core\Config;
use Bbs\Core\Db;

Config::loadSettings();

$args = array_slice($argv, 1);
$dry  = in_array('--dry-run', $args, true);
$out  = static fn (string $m) => fwrite(STDOUT, $m . "\n");

// optional: a source .ans path, and --max-rows=N to cap the height
$maxRows = 0;
$srcArg  = null;
foreach ($args as $a) {
    if (preg_match('/^--max-rows=(\d+)$/', $a, $m)) {
        $maxRows = (int) $m[1];
    } elseif ($a[0] !== '-') {
        $srcArg = $a;
    }
}
$src = $srcArg
    ? (str_starts_with($srcArg, '/') ? $srcArg : dirname(__DIR__) . '/' . ltrim($srcArg, './'))
    : dirname(__DIR__) . '/assets/' . Config::setting('banner_src', 'THUGSred.ans');

if (!is_file($src)) {
    fwrite(STDERR, "missing $src\n");
    exit(1);
}
$out('source: ' . $src . ($maxRows ? "  (cap {$maxRows} rows)" : ''));

/* ---- clean the ANSI art ------------------------------------------- */
$raw = (string) file_get_contents($src);
$raw = str_replace(["\r\n", "\r"], "\n", $raw);
// drop every CSI sequence that is NOT an SGR colour (…m): cursor moves,
// screen clears, DEC private modes (?25l/?25h), etc.
$raw = preg_replace('/\x1b\[\?[0-9;]*[A-Za-z]/', '', $raw) ?? $raw;
$raw = preg_replace('/\x1b\[[0-9;]*[A-Za-ln-z]/', '', $raw) ?? $raw;   // keeps ...m
$raw = rtrim($raw, "\n");

$vis    = static fn (string $l): int => mb_strlen((string) preg_replace('/\x1b\[[0-9;]*m/', '', $l));
$plain  = static fn (string $l): string => trim((string) preg_replace('/\x1b\[[0-9;]*m/', '', $l));
$lines  = explode("\n", $raw);

// drop a trailing "// ... ANSI" credit line some editors leave in
if ($lines && preg_match('~//.*ansi~i', $plain(end($lines)))) {
    array_pop($lines);
}
// trim leading / trailing blank lines, and collapse runs of blanks to one
while ($lines && $plain($lines[0]) === '') {
    array_shift($lines);
}
while ($lines && $plain(end($lines)) === '') {
    array_pop($lines);
}
$compact = [];
$blank = false;
foreach ($lines as $l) {
    $isBlank = $plain($l) === '';
    if ($isBlank && $blank) {
        continue;
    }
    $compact[] = $l;
    $blank = $isBlank;
}
$lines = $compact;
if ($maxRows > 0 && count($lines) > $maxRows) {
    $lines = array_slice($lines, 0, $maxRows);
}

$maxw = 0;
foreach ($lines as $l) {
    $maxw = max($maxw, $vis($l));
}
$width = Config::termCols();
$pad   = max(0, intdiv($width - $maxw, 2));
$body  = implode("\n", array_map(static fn (string $l): string => str_repeat(' ', $pad) . $l, $lines));

$out(sprintf('art: %d lines, %d cols wide, centred with %d-space margin for a %d-col screen',
    count($lines), $maxw, $pad, $width));

/* ---- preview ----------------------------------------------------- */
$out(str_repeat('-', min($width, 100)));
foreach (Frame::make('screen')->block($body, 'ansi')->toArray()['lines'] as $ln) {
    $s = '';
    foreach ($ln as $run) {
        $s .= $run['s'];
    }
    $out(rtrim($s));
}
$out(str_repeat('-', min($width, 100)));

if ($dry) {
    $out('--dry-run: no changes written.');
    exit(0);
}

/* ---- store the screen ------------------------------------------- */
Db::q(
    "INSERT INTO screens (slug, title, kind, content_type, body)
     VALUES ('art.logo', 'THUGS(red) - graffiti logo', 'ansi', 'ansi', ?)
     ON DUPLICATE KEY UPDATE title = VALUES(title), kind = VALUES(kind),
        content_type = VALUES(content_type), body = VALUES(body)",
    [$body]
);
$out('screen art.logo  ... stored');

/* ---- point the Main Menu at it -------------------------------- */
$n = Db::q("UPDATE menus SET header_screen = 'art.logo' WHERE slug = 'main' AND header_screen <> 'art.logo'")->rowCount();
$out('menu main header  ... ' . ($n ? 'repointed to art.logo' : 'already art.logo'));

/* ---- de-dupe the Connected/MOTD screen ------------------------ */
$motdSlug = Config::setting('motd_screen', 'boot.motd');
$motd = Db::one('SELECT body FROM screens WHERE slug = ?', [$motdSlug]);
if ($motd) {
    $b = (string) $motd['body'];
    // strip a leading top-border + block-font banner (up to the first ".---" rule)
    $trimmed = preg_replace('/^\|08\+=+\+\n(?:\|\d\d[^\n]*\n)+(?=\|08 \.-)/', '', $b, 1);
    if ($trimmed !== null && $trimmed !== $b) {
        Db::update('screens', ['body' => $trimmed], ['slug' => $motdSlug]);
        $out("screen $motdSlug  ... old inline banner removed");
    } else {
        $out("screen $motdSlug  ... no inline banner to remove (ok)");
    }
}

$out('done - the logo now renders from assets/THUGSred.ans on the Connected page and the Main Menu.');
