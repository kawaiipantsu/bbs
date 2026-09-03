<?php
/* THUGS(red) CP437 BBS banner -> assets/THUGSred_banner.ans */
declare(strict_types=1);

/* ---- 7-row block glyphs, 9 cols wide (thick 3-col strokes) -------- */
$G = [
'T' => ['█████████','   ███   ','   ███   ','   ███   ','   ███   ','   ███   ','   ███   '],
'H' => ['███   ███','███   ███','███   ███','█████████','███   ███','███   ███','███   ███'],
'U' => ['███   ███','███   ███','███   ███','███   ███','███   ███','███   ███',' ███████ '],
'G' => [' ████████','███      ','███      ','███  ████','███   ███','███   ███',' ███████ '],
'S' => [' ████████','███      ','███      ',' ███████ ','      ███','      ███','████████ '],
'R' => ['████████ ','███   ███','███   ███','████████ ','███ ███  ','███  ███ ','███   ███'],
'E' => ['█████████','███      ','███      ','███████  ','███      ','███      ','█████████'],
'D' => ['███████  ','███   ███','███    ██','███    ██','███    ██','███   ███','███████  '],
];
function word(array $G, string $w): array {
    $rows = array_fill(0, 7, '');
    foreach (str_split($w) as $ch) for ($r = 0; $r < 7; $r++) $rows[$r] .= $G[$ch][$r] . '  ';
    return array_map('rtrim', $rows);
}

$INNER  = 94;                 // cols between the ║ bars
$MARGIN = 3;

$thugs = word($G, 'THUGS');
$red   = word($G, 'RED');
$xThugs = 3;
$xBar   = $xThugs + max(array_map('mb_strlen', $thugs)) + 2;
$xRed   = $xBar + 3;

/* palette */
$BGa = 52;   $BGb = 88;      // faded gradient fill (very dark red / dark red)
$T1  = 208;  $T2  = 214;     // THUGS upper / lower
$BAR = 246;                  // divider
$RED = 196;                  // RED

$interior = [];
for ($r = 0; $r < 7; $r++) {
    // 1. mark every column the letters / divider will occupy
    $ink = array_fill(0, $INNER, false);
    $put = function (string $glyphRow, int $x0) use (&$ink, $INNER) {
        foreach (mb_str_split($glyphRow) as $i => $c) {
            $x = $x0 + $i;
            if ($x >= 0 && $x < $INNER && $c === '█') $ink[$x] = true;
        }
    };
    $put($thugs[$r], $xThugs);
    $put($red[$r], $xRed);
    if ($xBar < $INNER) $ink[$xBar] = true;

    // 2. lay the faded gradient everywhere EXCEPT a 1-col gutter around ink
    $ch = array_fill(0, $INNER, ' ');
    $fg = array_fill(0, $INNER, $BGb);
    for ($x = 0; $x < $INNER; $x++) {
        $near = ($ink[$x] ?? false) || ($ink[$x - 1] ?? false) || ($ink[$x + 1] ?? false);
        // also blank the counters/holes inside a glyph so letters read clean
        $lft = ($ink[$x - 1] ?? false) || ($ink[$x - 2] ?? false) || ($ink[$x - 3] ?? false);
        $rgt = ($ink[$x + 1] ?? false) || ($ink[$x + 2] ?? false) || ($ink[$x + 3] ?? false);
        if ($near || ($lft && $rgt)) { continue; }
        $d = abs($x - ($INNER - 1) / 2) / (($INNER - 1) / 2) + ((($x >> 1) + $r) % 5) * 0.02;
        if     ($d > 0.92) { $ch[$x] = ' '; }
        elseif ($d > 0.60) { $ch[$x] = '░'; $fg[$x] = $BGa; }
        elseif ($d > 0.30) { $ch[$x] = '▒'; $fg[$x] = $BGb; }
        else               { $ch[$x] = '▓'; $fg[$x] = $BGb; }
    }

    // 3. stamp the letters / divider on top
    $stamp = function (string $glyphRow, int $x0, int $color) use (&$ch, &$fg, $INNER) {
        foreach (mb_str_split($glyphRow) as $i => $c) {
            $x = $x0 + $i;
            if ($x >= 0 && $x < $INNER && $c === '█') { $ch[$x] = '█'; $fg[$x] = $color; }
        }
    };
    $stamp($thugs[$r], $xThugs, $r < 4 ? $T1 : $T2);
    if ($xBar < $INNER) { $ch[$xBar] = '┃'; $fg[$xBar] = $BAR; }
    $stamp($red[$r], $xRed, $RED);
    // coalesce into coloured runs
    $line = ''; $cur = null; $buf = '';
    for ($x = 0; $x < $INNER; $x++) {
        if ($fg[$x] !== $cur) { if ($buf !== '') $line .= "\x1b[38;5;{$cur}m$buf"; $buf = ''; $cur = $fg[$x]; }
        $buf .= $ch[$x];
    }
    if ($buf !== '') $line .= "\x1b[38;5;{$cur}m$buf";
    $interior[] = $line;
}

/* ---- rows: [colour, text] --------------------------------------- */
$SH = str_repeat(' ', 9);                          // flame crown: centred over the top box line
$rows = [];
$rows[] = [166, $SH . '   ▄▄     ▄      ▄▄▄      ▄      ▄▄     ▄      ▄▄▄      ▄      ▄▄     ▄     ▄▄▄'];
$rows[] = [202, $SH . ' ▟█▙▄▖ ▗▟█▙▖  ▖▗▟█▙▖   ▗▟█▙▄▖ ▗▟█▙▖  ▖ ▟█▙▄▖ ▗▟█▙▖  ▖▗▟█▙▖   ▗▟█▙▖ ▗▟█▙▄▖'];
$rows[] = [208, $SH . '▝████▄▄███████▄▄████████▄▄███████▄▄███████▄▄████████▄▄███████▄▄████████▄▄██▛'];
$rows[] = [214, $SH . '  ▀▀▜██████████████████████████████████████████████████████████████████▛▀▀'];
$rows[] = [240, '╔' . str_repeat('═', $INNER) . '╗'];
foreach ($interior as $ln) $rows[] = [88, '║' . $ln . "\x1b[38;5;240m║"];
$rows[] = [240, '╚' . str_repeat('═', $INNER) . '╝'];
$rows[] = [160, '    ▓▒░    ░▒▓░       ▒░         ░▒▓▒░        ▒░    ░▒▓░       ░▒░         ▒▓░'];
$rows[] = [52,  '     ▘      ▝          ▘           ▘▝          ▘      ▝         ▘           ▝'];
$tag = 'A   D A N I S H   ·   H A C K I N G   ·   C O M M U N I T Y';
$tl  = intdiv($INNER - mb_strlen($tag) - 4, 2);
$rows[] = [240, str_repeat('─', $tl) . '  ' . "\x1b[38;5;214m$tag" . "\x1b[38;5;240m" . '  ' . str_repeat('─', $INNER - $tl - 4 - mb_strlen($tag))];
$rows[] = [52,  '              ░░▒▒▓▓████████▓▓▒▒░░       ░▒▓██▓▒░       ░░▒▒▓▓████████▓▓▒▒░░'];

$L = str_repeat(' ', $MARGIN);
$out = '';
foreach ($rows as [$ci, $text]) $out .= $L . "\x1b[38;5;{$ci}m$text" . "\x1b[0m\r\n";
$path = '/var/www/vhosts-external/bbs.thugs.red/assets/THUGSred_banner.ans';
file_put_contents($path, $out);

$strip = fn ($s) => preg_replace('/\x1b\[[0-9;]*m/', '', $s);
$plain = array_map(fn ($r) => $strip($L . $r[1]), $rows);
printf("wrote %s : %d rows, widest %d, %d bytes\n\n", $path, count($rows), max(array_map('mb_strlen', $plain)), strlen($out));
foreach ($plain as $i => $l) printf("%2d|%s|\n", $i, $l);
