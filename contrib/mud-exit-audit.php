<?php
/**
 * THUGS(red) BBS - Hackers-MUD exit / connectivity audit.
 *
 * Read-only. Walks mud_rooms + mud_exits and reports:
 *   - rooms UNREACHABLE from the start room (vnum 1000), following every
 *     visible + hidden + locked exit
 *   - one-way / asymmetric exits (A->B with no matching B->A, or B's reverse
 *     pointing somewhere else)
 *   - dead-end rooms (no outbound exit at all)
 *   - hidden exits and locked exits (informational - a gate is not a break)
 *   - locked exits whose key item (key_vnum) has no matching item template
 *
 * Target end state: 0 unreachable, 0 unintended one-way. A deliberate one-way
 * is only legitimate if the $EX entry in mysql/mud_world*.php marks it
 * ['oneway' => true]; there are none at time of writing, so treat every
 * asymmetric exit below as a bug to fix in the $EX data or the exit builder.
 *
 *   php contrib/mud-exit-audit.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Core\Db;

$OPP = [
    'n' => 's', 's' => 'n', 'e' => 'w', 'w' => 'e', 'u' => 'd', 'd' => 'u',
    'ne' => 'sw', 'sw' => 'ne', 'nw' => 'se', 'se' => 'nw', 'in' => 'out', 'out' => 'in',
];

if (!Db::tableExists('mud_rooms') || (int) Db::val('SELECT COUNT(*) FROM mud_rooms') === 0) {
    fwrite(STDERR, "MUD world not built yet - run: php mysql/mud_world.php\n");
    exit(1);
}

$rooms = [];
foreach (Db::all('SELECT id, vnum, name, flags FROM mud_rooms') as $r) {
    $rooms[(int) $r['id']] = $r;
}
$ex = Db::all('SELECT * FROM mud_exits');
echo count($rooms) . ' rooms, ' . count($ex) . " exits\n";

$idx = [];
foreach ($ex as $e) {
    $idx[(int) $e['from_room']][$e['dir']] = $e;
}

/* -- one-way / asymmetric exits -------------------------------------- */
$oneway = [];
foreach ($ex as $e) {
    $f = (int) $e['from_room'];
    $t = (int) $e['to_room'];
    $d = $e['dir'];
    $opp = $OPP[$d] ?? null;
    if ($opp === null) {
        $oneway[] = "odd dir '$d': {$rooms[$f]['vnum']} -> {$rooms[$t]['vnum']}";
        continue;
    }
    if (!isset($idx[$t][$opp])) {
        $oneway[] = "{$rooms[$f]['vnum']} ({$rooms[$f]['name']}) --$d--> {$rooms[$t]['vnum']} ({$rooms[$t]['name']})  : NO $opp back";
    } elseif ((int) $idx[$t][$opp]['to_room'] !== $f) {
        $oneway[] = "{$rooms[$f]['vnum']} --$d--> {$rooms[$t]['vnum']} but its $opp goes to " . $rooms[(int) $idx[$t][$opp]['to_room']]['vnum'];
    }
}
echo "\n== " . count($oneway) . " one-way / asymmetric exits ==\n";
foreach ($oneway as $l) {
    echo "  $l\n";
}

/* -- rooms with no outbound exit ----------------------------------- */
$has = [];
foreach ($ex as $e) {
    $has[(int) $e['from_room']] = true;
}
$dead = [];
foreach ($rooms as $id => $r) {
    if (!isset($has[$id])) {
        $dead[] = "{$r['vnum']} ({$r['name']})";
    }
}
echo "\n== " . count($dead) . " rooms with NO outbound exit ==\n";
foreach ($dead as $l) {
    echo "  $l\n";
}

/* -- hidden / locked exits (informational) ------------------------- */
$hid = array_filter($ex, fn ($e) => (int) $e['hidden'] === 1);
$lok = array_filter($ex, fn ($e) => (int) $e['locked'] === 1);
echo "\n== " . count($hid) . " hidden exits ==\n";
foreach ($hid as $e) {
    echo "  {$rooms[(int) $e['from_room']]['vnum']} ({$rooms[(int) $e['from_room']]['name']}) --{$e['dir']}--> {$rooms[(int) $e['to_room']]['vnum']}  kw={$e['keyword']} hack_dc={$e['hack_dc']}\n";
}
echo "\n== " . count($lok) . " locked exits ==\n";
foreach ($lok as $e) {
    echo "  {$rooms[(int) $e['from_room']]['vnum']} ({$rooms[(int) $e['from_room']]['name']}) --{$e['dir']}--> {$rooms[(int) $e['to_room']]['vnum']}  kw={$e['keyword']} key_vnum=" . ($e['key_vnum'] ?? '-') . " hack_dc={$e['hack_dc']}\n";
}

/* -- locked exits whose key item template is missing -------------- */
$missingKey = [];
foreach ($lok as $e) {
    $kv = $e['key_vnum'];
    if ($kv === null || $kv === '' || (int) $kv === 0) {
        continue;
    }
    if ((int) Db::val('SELECT COUNT(*) FROM mud_item_templates WHERE vnum = ?', [(int) $kv]) === 0) {
        $missingKey[] = "{$rooms[(int) $e['from_room']]['vnum']} --{$e['dir']}--> {$rooms[(int) $e['to_room']]['vnum']}  needs key_vnum={$kv} which has NO item template";
    }
}
echo "\n== " . count($missingKey) . " locked exits with a missing key item ==\n";
foreach ($missingKey as $l) {
    echo "  $l\n";
}

/* -- rooms unreachable from the start (BFS from vnum 1000) -------- */
$start = null;
foreach ($rooms as $id => $r) {
    if ((int) $r['vnum'] === 1000) {
        $start = $id;
    }
}
$seen = $start !== null ? [$start => true] : [];
$q = $start !== null ? [$start] : [];
while ($q) {
    $cur = array_shift($q);
    foreach (($idx[$cur] ?? []) as $e) {
        $n = (int) $e['to_room'];
        if (!isset($seen[$n])) {
            $seen[$n] = true;
            $q[] = $n;
        }
    }
}
$unreach = [];
foreach ($rooms as $id => $r) {
    if (!isset($seen[$id])) {
        $unreach[] = "{$r['vnum']} ({$r['name']})";
    }
}
echo "\n== " . count($unreach) . " rooms UNREACHABLE from the start (following visible+hidden+locked exits) ==\n";
foreach ($unreach as $l) {
    echo "  $l\n";
}

echo "\n-- summary: " . count($unreach) . " unreachable, " . count($oneway) . " asymmetric, " . count($dead) . " dead-end, " . count($missingKey) . " missing-key --\n";
exit(($unreach || $oneway) ? 1 : 0);
