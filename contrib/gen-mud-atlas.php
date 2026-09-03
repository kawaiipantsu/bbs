<?php
/**
 * gen-mud-atlas.php - render the whole Hackers-MUD world as one big offline
 * reference map: every zone, every room, every exit, shops / safe rooms /
 * banks / boards / bosses, inter-district portals, a legend and a zone index.
 *
 *   php contrib/gen-mud-atlas.php
 *
 * Writes assets/hackers-mud-atlas.svg and (if rsvg-convert is present)
 * assets/hackers-mud-atlas.png.  This is a DEV/REFERENCE artifact - it shows
 * hidden passages too, unlike the in-game fog-of-war client map.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Core\Db;

$OUT_SVG = dirname(__DIR__) . '/assets/hackers-mud-atlas.svg';
$OUT_PNG = dirname(__DIR__) . '/assets/hackers-mud-atlas.png';

/* ----------------------------------------------------------------- data -- */

$zones = [];
foreach (Db::all('SELECT id, slug, name, level_min, level_max FROM mud_zones ORDER BY id') as $z) {
    $zones[(int) $z['id']] = [
        'id' => (int) $z['id'], 'slug' => $z['slug'], 'name' => $z['name'],
        'lmin' => (int) $z['level_min'], 'lmax' => (int) $z['level_max'], 'rooms' => [],
    ];
}

$rooms = [];
foreach (Db::all('SELECT id, vnum, zone_id, name, x, y, z, flags FROM mud_rooms') as $r) {
    $rid = (int) $r['id'];
    $rooms[$rid] = [
        'id' => $rid, 'vnum' => (int) $r['vnum'], 'zone' => (int) $r['zone_id'],
        'name' => $r['name'], 'x' => (int) $r['x'], 'y' => (int) $r['y'], 'z' => (int) $r['z'],
        'flags' => (string) $r['flags'], 'boss' => false, 'shop' => false,
        'px' => 0.0, 'py' => 0.0,
    ];
    if (isset($zones[(int) $r['zone_id']])) {
        $zones[(int) $r['zone_id']]['rooms'][] = $rid;
    }
}

foreach (Db::all('SELECT room_id, name FROM mud_shops') as $s) {
    if (isset($rooms[(int) $s['room_id']])) {
        $rooms[(int) $s['room_id']]['shop'] = true;
    }
}
foreach (Db::all(
    'SELECT DISTINCT mi.room_id FROM mud_mob_instances mi
     JOIN mud_mob_templates mt ON mt.id = mi.template_id
     WHERE mt.flags LIKE "%boss%" OR mt.behavior LIKE "%boss%"'
) as $b) {
    if (isset($rooms[(int) $b['room_id']])) {
        $rooms[(int) $b['room_id']]['boss'] = true;
    }
}

$exits = [];
foreach (Db::all('SELECT from_room, to_room, dir, keyword, locked, hidden FROM mud_exits') as $e) {
    $f = (int) $e['from_room'];
    $t = (int) $e['to_room'];
    if (!isset($rooms[$f], $rooms[$t])) {
        continue;
    }
    $exits[] = [
        'f' => $f, 't' => $t, 'dir' => $e['dir'], 'kw' => (string) $e['keyword'],
        'locked' => (int) $e['locked'] === 1, 'hidden' => (int) $e['hidden'] === 1,
    ];
}

/* --------------------------------------------------------------- layout -- */

// per-zone accent hue
$HUE = [
    1 => '#ff2d55', 2 => '#ff9b45', 3 => '#66e0ff', 4 => '#ff5555', 5 => '#b79a6b',
    6 => '#b98cff', 7 => '#ff5db1', 8 => '#e0b44a', 9 => '#4aa3ff', 10 => '#e6cf5a',
    11 => '#5be0a0', 12 => '#e0763a', 13 => '#33ff9e',
];
$hueOf = static fn (int $z): string => $HUE[$z] ?? '#8b90b2';

$CELL_W = 176.0;
$CELL_H = 150.0;
$PAD    = 30.0;      // inner panel padding
$HEADER = 46.0;      // panel header height
$GAPX   = 46.0;      // gap between panels
$GAPY   = 54.0;
$MARGIN = 60.0;      // canvas margin
$TOPBAR = 210.0;     // title band
$MAXROW = 5400.0;    // wrap panels past this x

/**
 * Project a zone's rooms onto an integer cell grid.  Flat zones use x / -y
 * (north up); predominantly-vertical zones (a tower) use x / -z.
 * Collisions are nudged onto a short spiral so nothing sits exactly on top
 * of another node.
 */
function layoutZone(array $zone, array &$rooms): array
{
    $ids = $zone['rooms'];
    if (!$ids) {
        return ['cols' => 1, 'rows' => 1];
    }
    $yspan = $zspan = 0;
    $ys = $zs = [];
    foreach ($ids as $id) {
        $ys[] = $rooms[$id]['y'];
        $zs[] = $rooms[$id]['z'];
    }
    $yspan = max($ys) - min($ys);
    $zspan = max($zs) - min($zs);
    $vertical = $zspan > $yspan;

    $taken = [];
    $minGX = $minGY = PHP_INT_MAX;
    $maxGX = $maxGY = PHP_INT_MIN;
    // deterministic order: by (vertical axis desc, then x)
    usort($ids, static function ($a, $b) use ($rooms, $vertical) {
        $va = $vertical ? -$rooms[$a]['z'] : -$rooms[$a]['y'];
        $vb = $vertical ? -$rooms[$b]['z'] : -$rooms[$b]['y'];
        return $va <=> $vb ?: ($rooms[$a]['x'] <=> $rooms[$b]['x']) ?: ($rooms[$a]['vnum'] <=> $rooms[$b]['vnum']);
    });
    foreach ($ids as $id) {
        $r = $rooms[$id];
        $gx = $r['x'];
        $gy = $vertical ? -$r['z'] : -$r['y'];
        if (!$vertical && $r['z'] !== 0) {
            $gx += $r['z'];           // shove alternate floors sideways
        }
        $spiral = [[0, 0], [1, 0], [0, 1], [1, 1], [-1, 0], [0, -1], [-1, 1], [1, -1], [-1, -1], [2, 0], [0, 2], [2, 1], [1, 2], [-2, 0], [0, -2], [2, 2], [-2, 1], [3, 0]];
        foreach ($spiral as [$dx, $dy]) {
            $k = ($gx + $dx) . ',' . ($gy + $dy);
            if (!isset($taken[$k])) {
                $gx += $dx;
                $gy += $dy;
                break;
            }
        }
        $taken[$gx . ',' . $gy] = true;
        $rooms[$id]['gx'] = $gx;
        $rooms[$id]['gy'] = $gy;
        $minGX = min($minGX, $gx);
        $maxGX = max($maxGX, $gx);
        $minGY = min($minGY, $gy);
        $maxGY = max($maxGY, $gy);
    }
    foreach ($ids as $id) {
        $rooms[$id]['gx'] -= $minGX;
        $rooms[$id]['gy'] -= $minGY;
    }
    return ['cols' => $maxGX - $minGX + 1, 'rows' => $maxGY - $minGY + 1, 'vertical' => $vertical];
}

// size every zone, then shelf-pack panels
$panels = [];
foreach ($zones as $zid => $zone) {
    if (!$zone['rooms']) {
        continue;
    }
    $g = layoutZone($zone, $rooms);
    $w = $g['cols'] * $CELL_W + $PAD * 2;
    $h = $g['rows'] * $CELL_H + $PAD * 2 + $HEADER;
    $panels[$zid] = ['zid' => $zid, 'w' => $w, 'h' => $h, 'cols' => $g['cols'], 'rows' => $g['rows'], 'vertical' => $g['vertical'] ?? false];
}
// biggest first -> tidier shelves
uasort($panels, static fn ($a, $b) => $b['h'] <=> $a['h']);

$cx = $MARGIN;
$cy = $MARGIN + $TOPBAR;
$shelfH = 0.0;
$canvasW = 0.0;
foreach ($panels as $zid => &$p) {
    if ($cx > $MARGIN && $cx + $p['w'] > $MAXROW) {
        $cx = $MARGIN;
        $cy += $shelfH + $GAPY;
        $shelfH = 0.0;
    }
    $p['x'] = $cx;
    $p['y'] = $cy;
    $cx += $p['w'] + $GAPX;
    $shelfH = max($shelfH, $p['h']);
    $canvasW = max($canvasW, $p['x'] + $p['w'] + $MARGIN);
}
unset($p);
$canvasH = $cy + $shelfH + $MARGIN + 120;
$canvasW = max($canvasW, 1600.0);

// resolve every room to an absolute pixel centre
foreach ($panels as $zid => $p) {
    foreach ($zones[$zid]['rooms'] as $id) {
        $rooms[$id]['px'] = $p['x'] + $PAD + ($rooms[$id]['gx'] + 0.5) * $CELL_W;
        $rooms[$id]['py'] = $p['y'] + $HEADER + $PAD + ($rooms[$id]['gy'] + 0.5) * $CELL_H;
    }
}

/* ------------------------------------------------------------------ svg -- */

$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
$shortName = static function (string $n): string {
    // drop a "Zone - " / "Zone: " prefix, collapse whitespace, clip
    $n = preg_replace('/^[A-Za-z\' ]+(?: - | : |: )/', '', $n) ?? $n;
    $n = trim(preg_replace('/\s+/', ' ', $n));
    return mb_strlen($n) > 26 ? mb_substr($n, 0, 25) . '…' : $n;
};

$svg = [];
$W = (int) ceil($canvasW);
$H = (int) ceil($canvasH);
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$W\" height=\"$H\" viewBox=\"0 0 $W $H\" font-family=\"'DejaVu Sans','Helvetica Neue',Arial,sans-serif\">";
$svg[] = <<<DEFS
<defs>
  <radialGradient id="bg" cx="50%" cy="0%" r="120%">
    <stop offset="0%" stop-color="#141026"/><stop offset="45%" stop-color="#0b0b16"/><stop offset="100%" stop-color="#05060c"/>
  </radialGradient>
  <pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse">
    <path d="M48 0H0V48" fill="none" stroke="#ffffff" stroke-opacity="0.035" stroke-width="1"/>
  </pattern>
  <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
    <path d="M0 0L10 5L0 10z" fill="#66e0ff" fill-opacity="0.75"/>
  </marker>
  <filter id="glow" x="-60%" y="-60%" width="220%" height="220%">
    <feGaussianBlur stdDeviation="3.2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
  </filter>
</defs>
DEFS;
$svg[] = "<rect width=\"$W\" height=\"$H\" fill=\"url(#bg)\"/>";
$svg[] = "<rect width=\"$W\" height=\"$H\" fill=\"url(#grid)\"/>";

/* --- title band --- */
$roomCt = count($rooms);
$exitCt = count($exits);
$zoneCt = count($panels);
$stamp  = date('Y-m-d');
$svg[] = "<g transform=\"translate($MARGIN,54)\">";
$svg[] = '<text x="0" y="0" fill="#ffffff" font-size="46" font-weight="800" letter-spacing="2" filter="url(#glow)">THUGS<tspan fill="#ff2d55">(red)</tspan> &#8226; HACKERS-MUD</text>';
$svg[] = '<text x="2" y="34" fill="#66e0ff" font-size="17" letter-spacing="4" font-weight="600">CITY ATLAS &#160;&#8212;&#160; OFFLINE REFERENCE</text>';
$svg[] = "<text x=\"2\" y=\"62\" fill=\"#8b90b2\" font-size=\"14\">$zoneCt districts &#183; $roomCt rooms &#183; $exitCt exits &#183; generated $stamp &#183; shows hidden passages (not the in-game fogged view)</text>";
$svg[] = '</g>';

/* --- legend (top-right) --- */
$lx = $W - $MARGIN - 470;
$svg[] = "<g transform=\"translate($lx,34)\" font-size=\"12.5\" fill=\"#c9cde0\">";
$svg[] = '<rect x="-16" y="-16" width="486" height="150" rx="10" fill="#0b0c16" fill-opacity="0.85" stroke="#242841"/>';
$svg[] = '<text x="0" y="4" fill="#8b90b2" font-size="11" letter-spacing="3" font-weight="700">LEGEND</text>';
$rowsL = [
    ['<circle cx="7" cy="0" r="7" fill="#0d0e18" stroke="#66e0ff" stroke-width="2"/>', 'room'],
    ['<circle cx="7" cy="0" r="8.5" fill="#ff2d55" stroke="#ff7a90" stroke-width="2"/>', 'boss room'],
    ['<circle cx="7" cy="0" r="7" fill="#0d0e18" stroke="#ffcf4a" stroke-width="2"/><circle cx="7" cy="0" r="11" fill="none" stroke="#ffcf4a" stroke-opacity="0.7"/>', 'shop (&#165;)'],
    ['<circle cx="7" cy="0" r="7" fill="#0d0e18" stroke="#3ce88b" stroke-width="2"/><text x="7" y="4" font-size="11" fill="#3ce88b" text-anchor="middle">&#9670;</text>', 'safe'],
    ['<text x="7" y="4" font-size="13" fill="#66e0ff" text-anchor="middle">$</text>', 'bank'],
    ['<text x="7" y="4" font-size="13" fill="#b98cff" text-anchor="middle">&#9636;</text>', 'board'],
    ['<line x1="-2" y1="0" x2="16" y2="0" stroke="#8b90b2" stroke-width="2"/>', 'exit'],
    ['<line x1="-2" y1="0" x2="16" y2="0" stroke="#ffcf4a" stroke-width="2" stroke-dasharray="4 3"/>', 'locked'],
    ['<line x1="-2" y1="0" x2="16" y2="0" stroke="#6b7192" stroke-width="2" stroke-dasharray="1 4" stroke-linecap="round"/>', 'hidden'],
    ['<text x="7" y="4" font-size="13" fill="#c9cde0" text-anchor="middle">&#9650;&#9660;</text>', 'up / down'],
    ['<line x1="-2" y1="0" x2="16" y2="0" stroke="#66e0ff" stroke-width="2" stroke-opacity="0.6" stroke-dasharray="6 5" marker-end="url(#arrow)"/>', 'district portal'],
    ['<circle cx="7" cy="0" r="7" fill="#0d0e18" stroke="#ffffff" stroke-width="2"/><text x="7" y="4" font-size="10" fill="#fff" text-anchor="middle">&#9733;</text>', 'start'],
];
$i = 0;
foreach ($rowsL as [$g, $lbl]) {
    $col = intdiv($i, 6);
    $row = $i % 6;
    $tx = $col * 240;
    $ty = 26 + $row * 19;
    $svg[] = "<g transform=\"translate($tx,$ty)\">$g<text x=\"26\" y=\"4\">$lbl</text></g>";
    $i++;
}
$svg[] = '</g>';

/* --- helper: draw one exit segment --- */
$drawEx = static function (array $x, array $rooms) use (&$svg, $hueOf): void {
    $a = $rooms[$x['f']];
    $b = $rooms[$x['t']];
    $cross = $a['zone'] !== $b['zone'];
    $x1 = round($a['px'], 1);
    $y1 = round($a['py'], 1);
    $x2 = round($b['px'], 1);
    $y2 = round($b['py'], 1);
    if ($cross) {
        // faint straight portal line so intra-district structure still reads
        $svg[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#66e0ff" stroke-opacity="0.28" stroke-width="1.6" stroke-dasharray="6 5" marker-end="url(#arrow)"/>',
            $x1, $y1, $x2, $y2
        );
        return;
    }
    if ($x['dir'] === 'u' || $x['dir'] === 'd' || $x['dir'] === 'in' || $x['dir'] === 'out') {
        $g = ['u' => '&#9650;', 'd' => '&#9660;', 'in' => '&#8901;in', 'out' => '&#8901;out'][$x['dir']];
        $svg[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-opacity="0.35" stroke-width="1.4" stroke-dasharray="2 3"/>',
            $x1, $y1, $x2, $y2, $hueOf($a['zone'])
        );
        $svg[] = sprintf('<text x="%s" y="%s" font-size="12" fill="#c9cde0" text-anchor="middle">%s</text>', round(($x1 + $x2) / 2, 1), round(($y1 + $y2) / 2 + 4, 1), $g);
        return;
    }
    if ($x['hidden']) {
        $svg[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#6b7192" stroke-width="1.8" stroke-opacity="0.8" stroke-dasharray="1 4" stroke-linecap="round"/>',
            $x1, $y1, $x2, $y2
        );
        return;
    }
    if ($x['locked']) {
        $svg[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#ffcf4a" stroke-width="2" stroke-opacity="0.85" stroke-dasharray="4 3"/>',
            $x1, $y1, $x2, $y2
        );
        return;
    }
    $svg[] = sprintf(
        '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5" stroke-opacity="0.5"/>',
        $x1, $y1, $x2, $y2, $hueOf($a['zone'])
    );
};

/* --- panels --- */
foreach ($panels as $zid => $p) {
    $zone = $zones[$zid];
    $hue  = $hueOf($zid);
    $px = round($p['x'], 1);
    $py = round($p['y'], 1);
    $pw = round($p['w'], 1);
    $ph = round($p['h'], 1);
    $svg[] = "<g>";
    $svg[] = "<rect x=\"$px\" y=\"$py\" width=\"$pw\" height=\"$ph\" rx=\"14\" fill=\"#0b0c16\" fill-opacity=\"0.72\" stroke=\"$hue\" stroke-opacity=\"0.55\" stroke-width=\"1.5\"/>";
    $svg[] = sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="14" fill="%s" fill-opacity="0.10"/>', $px, $py, $pw, round($HEADER, 1), $hue);
    $svg[] = sprintf('<text x="%s" y="%s" font-size="19" font-weight="800" fill="%s" letter-spacing="1">%s</text>', round($px + 18, 1), round($py + 30, 1), $hue, $esc(mb_strtoupper($zone['name'])));
    $svg[] = sprintf('<text x="%s" y="%s" font-size="12" fill="#8b90b2" text-anchor="end">L%d&#8211;%d &#183; %d rooms%s</text>', round($px + $pw - 16, 1), round($py + 29, 1), $zone['lmin'], $zone['lmax'], count($zone['rooms']), $p['vertical'] ? ' &#183; vertical (floors)' : '');
    $svg[] = "</g>";
}

/* --- intra-zone exits (under nodes) --- */
$svg[] = '<g>';
foreach ($exits as $x) {
    if ($rooms[$x['f']]['zone'] === $rooms[$x['t']]['zone']) {
        $drawEx($x, $rooms);
    }
}
$svg[] = '</g>';

/* --- cross-zone portals (over intra lines, under nodes) --- */
$svg[] = '<g>';
$seenPortal = [];
foreach ($exits as $x) {
    if ($rooms[$x['f']]['zone'] === $rooms[$x['t']]['zone']) {
        continue;
    }
    $k = min($x['f'], $x['t']) . '-' . max($x['f'], $x['t']);
    if (isset($seenPortal[$k])) {
        continue;               // one line per connected pair
    }
    $seenPortal[$k] = true;
    $drawEx($x, $rooms);
}
$svg[] = '</g>';

/* --- room nodes + labels --- */
foreach ($panels as $zid => $p) {
    $hue = $hueOf($zid);
    $svg[] = '<g>';
    foreach ($zones[$zid]['rooms'] as $id) {
        $r = $rooms[$id];
        $x = round($r['px'], 1);
        $y = round($r['py'], 1);
        $f = " {$r['flags']} ";
        $isStart = $r['vnum'] === 1000 || str_contains($f, ' start ');
        $isSafe  = str_contains($f, ' safe ');
        $isBank  = str_contains($f, ' bank ');
        $isBoard = str_contains($f, ' board ');

        if ($r['shop']) {
            $svg[] = sprintf('<circle cx="%s" cy="%s" r="11.5" fill="none" stroke="#ffcf4a" stroke-opacity="0.75"/>', $x, $y);
        }
        if ($r['boss']) {
            $svg[] = sprintf('<circle cx="%s" cy="%s" r="9" fill="#ff2d55" stroke="#ff9db0" stroke-width="2" filter="url(#glow)"/>', $x, $y);
        } elseif ($isStart) {
            $svg[] = sprintf('<circle cx="%s" cy="%s" r="8" fill="#0d0e18" stroke="#ffffff" stroke-width="2.5"/><text x="%s" y="%s" font-size="11" fill="#fff" text-anchor="middle">&#9733;</text>', $x, $y, $x, round($y + 4, 1));
        } else {
            $svg[] = sprintf('<circle cx="%s" cy="%s" r="7" fill="#0d0e18" stroke="%s" stroke-width="2"/>', $x, $y, $hue);
        }
        // corner glyphs
        if ($isSafe && !$r['boss']) {
            $svg[] = sprintf('<text x="%s" y="%s" font-size="10" fill="#3ce88b" text-anchor="middle">&#9670;</text>', $x, round($y + 3.4, 1));
        }
        if ($r['shop']) {
            $svg[] = sprintf('<text x="%s" y="%s" font-size="11" fill="#ffcf4a" font-weight="700">&#165;</text>', round($x + 10, 1), round($y - 8, 1));
        }
        if ($isBank) {
            $svg[] = sprintf('<text x="%s" y="%s" font-size="12" fill="#66e0ff" font-weight="700">$</text>', round($x + 10, 1), round($y + 12, 1));
        }
        if ($isBoard) {
            $svg[] = sprintf('<text x="%s" y="%s" font-size="11" fill="#b98cff">&#9636;</text>', round($x - 18, 1), round($y + 12, 1));
        }

        $label = $esc($shortName($r['name']));
        $svg[] = sprintf(
            '<text x="%s" y="%s" font-size="9" fill="#aab0c8" text-anchor="middle">%s</text>',
            $x, round($y + 22, 1), $label
        );
        $svg[] = sprintf(
            '<text x="%s" y="%s" font-size="7.5" fill="#5b6086" text-anchor="middle">%d</text>',
            $x, round($y - 12, 1), $r['vnum']
        );
    }
    $svg[] = '</g>';
}

/* --- zone index (bottom strip) --- */
$iy = $canvasH - 92;
$svg[] = "<g transform=\"translate($MARGIN,$iy)\" font-size=\"12\">";
$svg[] = '<text x="0" y="0" fill="#8b90b2" font-size="11" letter-spacing="3" font-weight="700">DISTRICT INDEX</text>';
$col = 0;
$row = 0;
$k = 0;
foreach ($zones as $zid => $z) {
    if (!$z['rooms']) {
        continue;
    }
    $tx = $col * 430;
    $ty = 20 + $row * 20;
    $vn = array_map(static fn ($id) => $rooms[$id]['vnum'], $z['rooms']);
    $rng = min($vn) . '&#8211;' . max($vn);
    $svg[] = sprintf(
        '<g transform="translate(%d,%d)"><rect x="0" y="-9" width="12" height="12" rx="3" fill="%s"/><text x="20" y="1" fill="#c9cde0">%s</text><text x="215" y="1" fill="#6b7192">L%d&#8211;%d</text><text x="300" y="1" fill="#6b7192">vnum %s</text></g>',
        $tx, $ty, $hueOf($zid), $esc($z['name']), $z['lmin'], $z['lmax'], $rng
    );
    $k++;
    $row = $k % 5;
    if ($row === 0) {
        $col++;
    }
}
$svg[] = '</g>';

$svg[] = '</svg>';

file_put_contents($OUT_SVG, implode("\n", $svg));
@chmod($OUT_SVG, 0664);
echo "wrote $OUT_SVG  (" . number_format(filesize($OUT_SVG) / 1024, 1) . " KB, {$W}x{$H})\n";

/* --------------------------------------------------------------- raster -- */
$rsvg = trim((string) @shell_exec('command -v rsvg-convert'));
if ($rsvg !== '') {
    $cmd = sprintf('%s -w %d --background-color=%s %s -o %s 2>&1', escapeshellarg($rsvg), min($W, 8000), escapeshellarg('#05060c'), escapeshellarg($OUT_SVG), escapeshellarg($OUT_PNG));
    $o = (string) shell_exec($cmd);
    if (is_file($OUT_PNG)) {
        @chmod($OUT_PNG, 0664);
        echo "wrote $OUT_PNG  (" . number_format(filesize($OUT_PNG) / 1024, 1) . " KB)\n";
    } else {
        echo "rsvg-convert failed: $o\n";
    }
} else {
    echo "rsvg-convert not found - SVG only\n";
}
