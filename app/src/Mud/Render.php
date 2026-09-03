<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * All the on-screen views: the room, the local ASCII map, inventory, the score
 * / character sheet, and item / mob inspection. Everything comes back as an
 * array of pipe-coded lines.
 */
final class Render
{
    private static function wrap(string $s, int $w = 92): array
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', $s)) as $para) {
            foreach (explode("\n", wordwrap($para, $w, "\n", true)) as $l) {
                $out[] = $l;
            }
        }
        return $out;
    }

    /** Public word-wrap for callers outside this class. @return list<string> */
    public static function wrapText(string $s, int $w = 92): array
    {
        return self::wrap($s, $w);
    }

    /** @return list<string> */
    public static function room(array $p, ?array $room = null, bool $brief = false): array
    {
        $room = $room ?: World::room((int) $p['room_id']);
        if (!$room) {
            return ['|12You are nowhere. That is a bug. Type |15recall|12.'];
        }
        $dark = str_contains($room['flags'], 'dark') && !self::hasLight($p);
        $out = ['|14' . $room['name'] . ($room['flags'] && str_contains($room['flags'], 'safe') ? '   |08[safe]' : '')];
        if ($dark) {
            $out[] = '|08It is pitch black. You can barely see your own hands.';
        } elseif (!$brief) {
            foreach (self::wrap($room['description']) as $l) {
                $out[] = '|07' . $l;
            }
            // a line of weather/time flavour for the outdoors
            if (!str_contains($room['flags'], 'indoors') && !str_contains($room['flags'], 'safe')) {
                $out[] = \Bbs\Mud\Mud::daylight()[1];
            }
        }

        // exits
        $ex = World::exits((int) $room['id']);
        $names = [];
        foreach ($ex as $dir => $x) {
            if ($x['hidden']) {
                continue;
            }
            $n = World::DIRS[$dir] ?? $dir;
            if ($x['keyword'] && ($x['locked'] || $x['hack_dc'])) {
                $n = '(' . $n . ')';
            }
            $names[] = $n;
        }
        $out[] = '|08[ Exits: |10' . (implode(' ', $names) ?: 'none') . '|08 ]';

        if ($dark) {
            return $out;
        }

        foreach (World::items('room', (int) $room['id']) as $it) {
            $out[] = '|11' . ($it['tpl']['room_desc'] ?: 'There is ' . $it['tpl']['name'] . ' here.');
        }
        foreach (World::mobs((int) $room['id']) as $mi) {
            if ($mi['state'] === 'dead') {
                continue;
            }
            $tag = $mi['state'] === 'fighting' ? ' |12[fighting]' : '';
            $hpq = (int) $mi['hp'] < (int) $mi['tpl']['max_hp'] * 0.35 ? ' |08(hurt)' : '';
            $out[] = '|09' . $mi['tpl']['room_desc'] . $tag . $hpq;
        }
        foreach (World::players((int) $room['id'], (int) $p['id']) as $op) {
            $out[] = '|13' . $op['name'] . ($op['title'] ? ', ' . $op['title'] : '') . ' is here.';
        }
        return $out;
    }

    public static function hasLight(array $p): bool
    {
        foreach (Player::equipment((int) $p['id']) as $eq) {
            if ($eq['tpl']['type'] === 'light' || str_contains($eq['tpl']['flags'], 'glow')) {
                return true;
            }
        }
        $room = World::room((int) $p['room_id']);
        return $room && (int) $room['light'] === 1;
    }

    /** Local ASCII minimap of the current z-level. @return list<string> */
    public static function map(array $p): array
    {
        $room = World::room((int) $p['room_id']);
        if (!$room) {
            return [];
        }
        $z = (int) $room['z'];
        $cx = (int) $room['x'];
        $cy = (int) $room['y'];
        $R = 6;
        $visited = json_decode($p['data'] ?? '{}', true)['visited'] ?? [];
        $visited = array_flip($visited);

        // rooms in range on this level
        $rooms = Db::all(
            'SELECT id, x, y, name FROM mud_rooms WHERE zone_id = ? AND z = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?',
            [$room['zone_id'], $z, $cx - $R, $cx + $R, $cy - $R, $cy + $R]
        );
        $at = [];
        foreach ($rooms as $r) {
            $at[(int) $r['x']][(int) $r['y']] = $r;
        }
        // exit connectors
        $exOf = [];
        foreach ($rooms as $r) {
            $exOf[(int) $r['id']] = World::exits((int) $r['id']);
        }

        $W = $R * 2 + 1;
        $grid = [];
        for ($row = 0; $row < $W * 2 - 1; $row++) {
            $grid[$row] = array_fill(0, $W * 4 - 3, ' ');
        }
        foreach ($rooms as $r) {
            $gx = ((int) $r['x'] - ($cx - $R)) * 4;
            $gy = (($cy + $R) - (int) $r['y']) * 2;
            if ($gy < 0 || $gy >= count($grid) || $gx < 0 || $gx + 2 >= count($grid[0])) {
                continue;
            }
            $known = isset($visited[(int) $r['id']]);
            $mark = (int) $r['id'] === (int) $room['id'] ? '@' : ($known ? '·' : '?');
            $grid[$gy][$gx] = $known || $mark === '@' ? '[' : ' ';
            $grid[$gy][$gx + 1] = $mark;
            $grid[$gy][$gx + 2] = $known || $mark === '@' ? ']' : ' ';
            if (!$known && $mark !== '@') {
                continue;
            }
            foreach ($exOf[(int) $r['id']] ?? [] as $dir => $x) {
                if ($dir === 'e' && $gx + 3 < count($grid[0])) {
                    $grid[$gy][$gx + 3] = '-';
                }
                if ($dir === 'w' && $gx - 1 >= 0) {
                    $grid[$gy][$gx - 1] = '-';
                }
                if ($dir === 'n' && $gy - 1 >= 0) {
                    $grid[$gy - 1][$gx + 1] = '|';
                }
                if ($dir === 's' && $gy + 1 < count($grid)) {
                    $grid[$gy + 1][$gx + 1] = '|';
                }
            }
        }
        $out = ['|14Local map  |08(' . $room['name'] . ', level ' . $z . ')', '|08+' . str_repeat('-', $W * 4 - 3) . '+'];
        foreach ($grid as $gr) {
            $line = implode('', $gr);
            $line = str_replace(['@'], ['|15@|09'], $line);
            $out[] = '|08|' . '|09' . rtrim($line) . str_repeat(' ', max(0, $W * 4 - 3 - mb_strlen(rtrim($line)))) . '|08|';
        }
        $out[] = '|08+' . str_repeat('-', $W * 4 - 3) . '+';
        $out[] = '|08@ you   · been there   ? unexplored';
        $up = World::exits((int) $room['id']);
        if (isset($up['u']) || isset($up['d'])) {
            $out[] = '|08(also: ' . (isset($up['u']) ? 'up ' : '') . (isset($up['d']) ? 'down' : '') . ')';
        }
        return $out;
    }

    /** @return list<string> */
    public static function score(array $p): array
    {
        $st = Player::effectiveStats($p);
        $base = json_decode($p['stats'] ?? '{}', true) ?: [];
        $need = Player::xpForLevel((int) $p['level'] + 1);
        $have = (int) $p['xp'];
        $prev = Player::xpForLevel((int) $p['level']);
        $pct = $need > $prev ? max(0, min(1, ($have - $prev) / ($need - $prev))) : 1;
        $bar = fn ($v, $mx, $c) => $c . str_repeat('#', (int) round(20 * $v / max(1, $mx))) . '|08' . str_repeat('.', 20 - (int) round(20 * $v / max(1, $mx)));

        $arch = Player::ARCHETYPES[$p['archetype']]['name'] ?? ucfirst($p['archetype']);
        $out = [
            '|08.-------------------------------------------------------------------.',
            sprintf('|08| |15%-24s |08%-10s  |08Lv |14%-3d |08cred |13%-6d |08|', $p['name'], $arch, $p['level'], $p['street_cred']),
            '|08+-------------------------------------------------------------------+',
            sprintf('|08| HP    %s |15%4d/%-4d |08|', $bar((int) $p['hp'], (int) $p['max_hp'], '|12'), $p['hp'], $p['max_hp']),
            sprintf('|08| Heat  %s |15%4d/%-4d |08|', $bar((int) $p['energy'], (int) $p['max_energy'], '|14'), $p['energy'], $p['max_energy']),
            sprintf('|08| XP    %s |15%3d%%     |08|', $bar((int) round($pct * 100), 100, '|10'), (int) round($pct * 100)),
            '|08+-------------------------------------------------------------------+',
        ];
        $line = '|08| ';
        foreach (Player::STATS as $s) {
            $delta = $st[$s] - (int) ($base[$s] ?? $st[$s]);
            $line .= sprintf('|07%-7s|15%2d', ucfirst($s), $st[$s]);
            $line .= $delta ? sprintf('|10%+d ', $delta) : '   ';
        }
        $out[] = rtrim($line) . str_repeat(' ', max(0, 67 - mb_strlen(preg_replace('/\|\d\d/', '', rtrim($line))))) . '|08|';
        $out[] = sprintf('|08| |08Eddies |14¥%-10d |08Bank |14¥%-10d |08Unspent pts |15%-3d |08|',
            $p['money'], $p['bank'], $p['unspent_points']);
        $out[] = '|08+-------------------------------------------------------------------+';

        // skills
        $sk = Db::all('SELECT skill, level FROM mud_player_skills WHERE player_id = ? ORDER BY level DESC, skill', [$p['id']]);
        $skStr = [];
        foreach ($sk as $s) {
            $skStr[] = sprintf('%s %d', $s['skill'], $s['level']);
        }
        foreach (self::wrap('Skills: ' . implode('  ', $skStr), 62) as $l) {
            $out[] = '|08| |07' . str_pad($l, 63) . '|08|';
        }

        // effects
        $ef = Player::effects((int) $p['id']);
        if ($ef) {
            $es = [];
            foreach ($ef as $e) {
                $es[] = $e['name'] . '(' . max(0, strtotime($e['expires_at']) - time()) . 's)';
            }
            foreach (self::wrap('Active: ' . implode('  ', $es), 62) as $l) {
                $out[] = '|08| |11' . str_pad($l, 63) . '|08|';
            }
        }
        $out[] = "|08| |08" . str_pad("hunger {$p['hunger']}  thirst {$p['thirst']}  kills {$p['kills']}  deaths {$p['deaths']}", 63) . "|08|";
        $wl = Player::wanted($p);
        if ($wl > 0) {
            $tier = $wl >= 60 ? '|12MAXTAC RESPONSE' : ($wl >= 20 ? '|09WANTED' : '|11flagged');
            $out[] = '|08| ' . str_pad("NCPD heat: $tier |08($wl/100)", 72) . '|08|';
        }
        $out[] = '|08`-------------------------------------------------------------------\'';
        return $out;
    }

    /** @return list<string> */
    public static function inventory(array $p): array
    {
        $out = ['|14Inventory  |08(' . number_format(Player::carryWeight((int) $p['id']), 1) . ' / '
            . number_format(Player::maxCarry($p), 0) . ' kg)'];
        $inv = Player::inventory((int) $p['id']);
        if (!$inv) {
            $out[] = '|08  ...empty. A true minimalist.';
        }
        $grouped = [];
        foreach ($inv as $i) {
            $grouped[$i['tpl']['name']][] = $i;
        }
        foreach ($grouped as $name => $list) {
            $out[] = '|07  ' . $name . (count($list) > 1 ? ' |08(x' . count($list) . ')' : '');
        }
        $out[] = '';
        $out[] = '|14Worn / wired';
        $slots = array_merge(['wield', 'held'], Player::WEAR_SLOTS, Player::IMPLANT_SLOTS);
        $any = false;
        foreach ($slots as $slot) {
            $eq = Player::equipmentSlot((int) $p['id'], $slot);
            if ($eq) {
                $any = true;
                $label = str_starts_with($slot, 'implant_') ? '<' . substr($slot, 8) . ' implant>' : '<' . $slot . '>';
                $out[] = sprintf('|08  %-20s |10%s', $label, $eq['tpl']['name']);
            }
        }
        if (!$any) {
            $out[] = '|08  nothing equipped';
        }
        $out[] = '|08  Eddies on hand: |14¥' . $p['money'];
        return $out;
    }

    /** @return list<string> */
    public static function examineItem(array $tpl): array
    {
        $out = ['|14' . ucfirst($tpl['name'])];
        foreach (self::wrap($tpl['long_desc']) as $l) {
            $out[] = '|07' . $l;
        }
        $bits = ['|08type: |07' . $tpl['type']];
        if ($tpl['slot']) {
            $bits[] = '|08slot: |07' . $tpl['slot'];
        }
        if ($tpl['damage_dice']) {
            $bits[] = '|08dmg: |07' . $tpl['damage_dice'];
        }
        if ($tpl['armor']) {
            $bits[] = '|08armor: |07' . $tpl['armor'];
        }
        if ((int) $tpl['level_req'] > 1) {
            $bits[] = '|08lvl req: |07' . $tpl['level_req'];
        }
        $bits[] = '|08value: |14¥' . $tpl['value'];
        $bits[] = '|08weight: |07' . rtrim(rtrim(number_format((float) $tpl['weight'], 2), '0'), '.') . 'kg';
        $out[] = implode('   ', $bits);
        if ($tpl['stat_mods']) {
            $m = [];
            foreach ($tpl['stat_mods'] as $k => $v) {
                $m[] = sprintf('%s %+d', $k, $v);
            }
            $out[] = '|10mods: ' . implode('  ', $m);
        }
        if ($tpl['effect']) {
            $e = $tpl['effect'];
            if (isset($e['heal'])) {
                $out[] = '|10restores ' . $e['heal'] . ' HP';
            }
            if (isset($e['energy'])) {
                $out[] = '|10restores ' . $e['energy'] . ' heat';
            }
            if (isset($e['food'])) {
                $out[] = '|10sates hunger';
            }
            if (isset($e['drink'])) {
                $out[] = '|10quenches thirst';
            }
            if (isset($e['buff'])) {
                $out[] = '|11pre-fight buff: ' . $e['buff']['name'] . ' for ' . $e['buff']['secs'] . 's';
            }
        }
        if (str_contains($tpl['flags'], 'illegal')) {
            $out[] = '|09[ illegal - carrying this near NCPD is a bad idea ]';
        }
        return $out;
    }

    /** @return list<string> */
    public static function examineMob(array $mi): array
    {
        $t = $mi['tpl'];
        $out = ['|09' . ucfirst($t['name'])];
        foreach (self::wrap($t['long_desc']) as $l) {
            $out[] = '|07' . $l;
        }
        $hpPct = (int) round(100 * (int) $mi['hp'] / max(1, (int) $t['max_hp']));
        $cond = $hpPct > 90 ? 'in perfect shape' : ($hpPct > 60 ? 'lightly wounded' : ($hpPct > 30 ? 'badly hurt' : 'barely standing'));
        $out[] = "|08It is $cond.  |08faction: |07{$t['faction']}  |08level: |07{$t['level']}";
        if (str_contains($t['behavior'], 'aggressive')) {
            $out[] = '|12It looks like it wants to hurt you.';
        }
        return $out;
    }
}
