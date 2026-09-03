<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * Hackers-MUD command hub. `command()` parses a line and dispatches to a
 * cmd_* handler; everything returns an array of pipe-coded output lines.
 * State lives in the mud_* tables - this class is (mostly) stateless.
 */
final class Mud
{
    /* canonical verb => alias list */
    private const VERBS = [
        'look'      => ['l'],
        'examine'   => ['exa', 'x', 'inspect', 'read'],
        'map'       => ['m'],
        'score'     => ['sc', 'stats', 'sheet', 'char'],
        'inventory' => ['i', 'inv'],
        'equipment' => ['eq', 'worn'],
        'who'       => [],
        'get'       => ['take', 'g', 'grab'],
        'drop'      => ['dr'],
        'put'       => [],
        'give'      => [],
        'wear'      => [],
        'wield'     => [],
        'hold'      => [],
        'implant'   => ['install'],
        'remove'    => ['rem', 'unwield', 'unequip'],
        'use'       => ['activate'],
        'eat'       => [],
        'drink'     => [],
        'inject'    => ['apply', 'pop'],
        'kill'      => ['k', 'attack', 'hit', 'fight'],
        'flee'      => ['fl', 'run'],
        'consider'  => ['con'],
        'list'      => ['wares'],
        'buy'       => [],
        'sell'      => [],
        'value'     => ['appraise'],
        'talk'      => ['ask', 'greet'],
        'say'       => [],
        'emote'     => [],
        'rob'       => ['pickpocket', 'mug', 'steal'],
        'hack'      => ['breach', 'jack'],
        'rest'      => [],
        'sleep'     => [],
        'wake'      => [],
        'sit'       => [],
        'stand'     => [],
        'recall'    => ['home', 'safehouse'],
        'deposit'   => [],
        'withdraw'  => [],
        'spend'     => ['raise'],
        'train'     => ['practice'],
        'quests'    => ['quest', 'journal', 'jobs', 'job'],
        'accept'    => [],
        'board'     => ['bounties', 'bounty', 'jobboard'],
        'scan'      => ['peek'],
        'uninstall' => ['extract', 'remove-chrome'],
        'bribe'     => ['payoff', 'launder'],
        'help'      => ['commands'],
        'time'      => [],
        'feed'      => [],   // world feed / recent events
    ];

    /* ---- sound effects: handlers push names, MudModule drains them ---- */
    /** @var list<string> */
    private static array $sfx = [];

    public static function sfx(string ...$names): void
    {
        foreach ($names as $n) {
            self::$sfx[] = $n;
        }
    }

    /** @return list<string> the queued effect names, cleared. */
    public static function takeSfx(): array
    {
        $s = self::$sfx;
        self::$sfx = [];
        return array_slice($s, 0, 6);
    }

    /** Looping ambient bed key for a room: rain|hum|drip|wind|static|room. */
    public static function ambientFor(int $roomId): string
    {
        $room = World::room($roomId);
        if (!$room) {
            return 'room';
        }
        $flags = (string) $room['flags'];
        if (str_contains($flags, 'net')) {
            return 'static';
        }
        $zone = (string) Db::val('SELECT slug FROM mud_zones WHERE id = ?', [$room['zone_id']]);
        return match ($zone) {
            'undercity'  => 'drip',
            'badlands'   => 'wind',
            'blackwall'  => 'static',
            'arcade'     => 'room',
            'corpo'      => str_contains($flags, 'indoors') ? 'hum' : 'rain',
            default      => str_contains($flags, 'indoors') ? 'room' : 'rain',
        };
    }

    /* ---- session entry ------------------------------------------------ */

    public static function open(int $userId, string $handle): array
    {
        Tick::maybeRun();
        $p = Player::forUser($userId);
        if (!$p) {
            return ['phase' => 'archetype', 'lines' => self::archetypeScreen($handle)];
        }
        // clamp a stuck combat state on reconnect
        if ($p['state'] === 'fighting') {
            $mi = $p['target_mob'] ? World::mobInstance((int) $p['target_mob']) : null;
            if (!$mi || $mi['state'] === 'dead' || (int) $mi['room_id'] !== (int) $p['room_id']) {
                Db::q("UPDATE mud_players SET state='idle', target_mob=NULL WHERE id=?", [$p['id']]);
                $p = Player::byId((int) $p['id']);
            }
        }
        $out = ['|10Jacked in.  |08(type |15help|08 for commands, |15look|08 to see where you are)'];
        $out = array_merge($out, Tick::drain($p), [''], self::look($p));
        return ['phase' => 'play', 'player_id' => (int) $p['id'], 'lines' => $out, 'prompt' => self::prompt($p)];
    }

    public static function archetypeScreen(string $handle): array
    {
        $out = [
            '|09  ┌───────────────────────────────────────────────────────────────┐',
            '|09  │  |15H A C K E R S - M U D|09   ::   pick your background          │',
            '|09  └───────────────────────────────────────────────────────────────┘',
            '',
            "|07  Night City eats people. You're going to eat it back, {$handle}.",
            '|07  Choose who you were before the Net swallowed you:',
            '',
        ];
        $i = 1;
        foreach (Player::ARCHETYPES as $slug => $a) {
            $s = $a['stats'];
            $out[] = sprintf('|08  [|15%d|08] |14%-10s  |08BOD %d REF %d INT %d COOL %d TECH %d   |07hp %d',
                $i++, $a['name'], $s['body'], $s['reflex'], $s['intel'], $s['cool'], $s['tech'], $a['hp']);
            $out[] = '|08       ' . $a['blurb'];
        }
        $out[] = '';
        $out[] = '|08  Type |151|08, |152|08 or |153|08 to begin.  Stats can be raised later.';
        return $out;
    }

    public static function chooseArchetype(int $userId, string $handle, string $choice): array
    {
        $keys = array_keys(Player::ARCHETYPES);
        $idx = ctype_digit(trim($choice)) ? (int) trim($choice) - 1 : array_search(strtolower(trim($choice)), $keys, true);
        if (!isset($keys[$idx])) {
            return ['done' => false, 'lines' => ['|08  Pick 1, 2 or 3.']];
        }
        $p = Player::create($userId, $handle, $keys[$idx]);
        $lines = array_merge(self::introLore(), [''], self::look($p));
        return ['done' => true, 'player_id' => (int) $p['id'], 'lines' => $lines, 'prompt' => self::prompt($p)];
    }

    private static function introLore(): array
    {
        return [
            '|08  ......................................................................',
            '|11  The elevator doors grind open on the ground floor of the Kabuki hab-',
            '|11  stack. Rain. Neon bleeding down the puddles. Somewhere a siren, then',
            '|11  nothing. Your new deck is warm against your ribs and the whole rotten',
            '|11  city is one long unpatched system waiting for someone with nerve.',
            '|08  ......................................................................',
            '',
            '|07  You have arrived.  |08(|15look|08 · |15map|08 · |15help|08)',
        ];
    }

    /* ---- the parser ------------------------------------------------- */

    public static function command(int $playerId, string $raw): array
    {
        Tick::maybeRun();
        $p = Player::byId($playerId);
        if (!$p) {
            return ['|12You are not jacked in.'];
        }
        Player::touch($playerId);
        $pending = Tick::drain($p);
        $p = Player::byId($playerId);

        $raw = trim($raw);
        if ($raw === '') {
            // blank line = advance combat / re-look
            $body = $p['state'] === 'fighting' && $p['target_mob']
                ? Combat::engage($playerId, (int) $p['target_mob'])
                : self::look(Player::byId($playerId));
            return array_merge($pending, $body);
        }

        // punctuation verbs
        if ($raw[0] === "'" || $raw[0] === ':') {
            $raw = ($raw[0] === "'" ? 'say ' : 'emote ') . ltrim(substr($raw, 1));
        }

        $parts = preg_split('/\s+/', $raw);
        $verb = strtolower(array_shift($parts));
        $args = $parts;
        if ($verb === '?') {
            $verb = 'help';
        }

        // direction shortcuts
        $dirmap = ['n' => 'n', 's' => 's', 'e' => 'e', 'w' => 'w', 'u' => 'u', 'd' => 'd',
                   'ne' => 'ne', 'nw' => 'nw', 'se' => 'se', 'sw' => 'sw', 'in' => 'in', 'out' => 'out',
                   'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w', 'up' => 'u', 'down' => 'd',
                   'northeast' => 'ne', 'northwest' => 'nw', 'southeast' => 'se', 'southwest' => 'sw'];
        if (isset($dirmap[$verb])) {
            return array_merge($pending, self::cmd_move($p, $dirmap[$verb]));
        }
        if ($verb === 'go' && $args && isset($dirmap[strtolower($args[0])])) {
            return array_merge($pending, self::cmd_move($p, $dirmap[strtolower($args[0])]));
        }
        if (($verb === 'enter' || $verb === 'exit') && !$args) {
            return array_merge($pending, self::cmd_move($p, $verb === 'enter' ? 'in' : 'out'));
        }

        // resolve verb -> handler
        $method = null;
        if (isset(self::VERBS[$verb])) {
            $method = 'cmd_' . $verb;
        } else {
            foreach (self::VERBS as $canon => $aliases) {
                if (in_array($verb, $aliases, true)) {
                    $method = 'cmd_' . $canon;
                    break;
                }
            }
        }
        if ($method === null && strlen($verb) >= 2) {
            foreach (array_keys(self::VERBS) as $canon) {
                if (str_starts_with($canon, $verb)) {
                    $method = 'cmd_' . $canon;
                    break;
                }
            }
        }
        if ($method === null || !method_exists(self::class, $method)) {
            return array_merge($pending, ['|08Huh?  Type |15help|08 for commands.']);
        }

        $out = self::$method($p, $args, $raw);
        return array_merge($pending, is_array($out) ? $out : ['|08...']);
    }

    /* ---- prompt --------------------------------------------------- */

    public static function prompt(array $p): string
    {
        $p = Player::byId((int) $p['id']) ?: $p;
        $tag = $p['state'] === 'fighting' ? ' |09*FIGHT*|07' : ($p['pos'] !== 'standing' ? ' |08(' . $p['pos'] . ')|07' : '');
        $w = Player::wanted($p);
        if ($w >= 60) {
            $tag .= ' |12*MAXTAC*|07';
        } elseif ($w >= 20) {
            $tag .= ' |09*WANTED*|07';
        }
        return sprintf('|08[|12%d|08/|12%d|08hp |14%d|08/|14%d|08he |14¥%d|08 lv|15%d|08]%s >',
            $p['hp'], $p['max_hp'], $p['energy'], $p['max_energy'], $p['money'], $p['level'], $tag);
    }

    /** Strip a leading "a "/"an "/"the " so it reads after another article. */
    public static function bare(string $name): string
    {
        return preg_replace('/^(a|an|the)\s+/i', '', $name) ?? $name;
    }

    /** Rough time-of-day for room flavour. */
    public static function daylight(): array
    {
        $h = (int) date('G');
        return match (true) {
            $h >= 5 && $h < 8   => ['dawn',  '|08The sky over the towers is going from black to bruise. Kabuki never fully wakes; it just changes shift.'],
            $h >= 8 && $h < 17  => ['day',   '|08Flat grey daylight. The neon looks tired and honest in it.'],
            $h >= 17 && $h < 20 => ['dusk',  '|08The light is failing and the signs are winning. Rush hour of a city that never really stops.'],
            default             => ['night', '|08Full dark, and the city is finally itself - all neon, rain and the far-off wow of a siren.'],
        };
    }

    /* =============================================================
     *  COMMAND HANDLERS
     * ============================================================= */

    private static function look(array $p, array $args = []): array
    {
        if ($args) {
            return self::cmd_examine($p, $args, '');
        }
        return Render::room($p);
    }
    private static function cmd_look(array $p, array $args): array { return self::look($p, $args); }

    private static function cmd_move(array $p, string $dir): array
    {
        if ($p['state'] === 'fighting') {
            return ['|12You are fighting! Break off with |15flee|12 first.'];
        }
        if (in_array($p['pos'], ['resting', 'sleeping', 'sitting'], true)) {
            return ['|08You need to |15stand|08 up first.'];
        }
        $ex = World::exits((int) $p['room_id']);
        if (!isset($ex[$dir])) {
            return ['|08You cannot go ' . (World::DIRS[$dir] ?? $dir) . '.'];
        }
        $x = $ex[$dir];
        if ($x['locked']) {
            return ['|09The way ' . (World::DIRS[$dir] ?? $dir) . ' is locked'
                . ($x['hack_dc'] ? '. |08(an electronic lock - try |15hack ' . ($x['keyword'] ?: 'door') . '|08)' : '.')];
        }
        $st = Player::effectiveStats($p);
        if (str_contains($x['descr'], 'climb') && random_int(1, 20) + intdiv($st['body'], 2) + Player::skill((int) $p['id'], 'athletics') < 12) {
            Player::trainSkill((int) $p['id'], 'athletics', 2);
            return ['|08You lose your grip and drop back. (try again)'];
        }
        Db::q('UPDATE mud_players SET room_id = ? WHERE id = ?', [$x['to_room'], $p['id']]);
        $p['room_id'] = $x['to_room'];
        Player::visit($p, (int) $x['to_room']);
        World::event((int) $p['id'], 'move', '');
        self::sfx($x['keyword'] || in_array($dir, ['in', 'out'], true) ? 'door' : (random_int(0, 1) ? 'step' : 'step2'));
        $out = ['|08You head ' . (World::DIRS[$dir] ?? $dir) . '.'];
        $out = array_merge($out, Render::room($p));
        // instant aggro on arrival
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if ($mi['state'] === 'idle' && str_contains((string) $mi['tpl']['behavior'], 'aggressive')
                && !str_contains((string) World::room((int) $p['room_id'])['flags'], 'safe') && !str_contains((string) World::room((int) $p['room_id'])['flags'], 'nomob')) {
                $out[] = '';
                $out = array_merge($out, Combat::mobStrike((int) $mi['id'], (int) $p['id']));
                break;
            }
        }
        return $out;
    }

    private static function cmd_map(array $p): array { return Render::map($p); }
    private static function cmd_score(array $p): array { return Render::score($p); }
    private static function cmd_inventory(array $p): array { return Render::inventory($p); }

    private static function cmd_equipment(array $p): array
    {
        $out = ['|14You are wearing / wired with:'];
        $any = false;
        foreach (array_merge(['wield', 'held'], Player::WEAR_SLOTS, Player::IMPLANT_SLOTS) as $slot) {
            $eq = Player::equipmentSlot((int) $p['id'], $slot);
            if ($eq) {
                $any = true;
                $label = str_starts_with($slot, 'implant_') ? '<' . substr($slot, 8) . ' cyber>' : '<' . $slot . '>';
                $out[] = sprintf('|08  %-18s |10%s', $label, $eq['tpl']['name']);
            }
        }
        if (!$any) {
            $out[] = '|08  nothing - you are basically naked out here';
        }
        return $out;
    }

    private static function cmd_who(array $p): array
    {
        $rows = Db::all("SELECT name, title, level, archetype FROM mud_players WHERE last_cmd_at > NOW() - INTERVAL 15 MINUTE ORDER BY level DESC");
        $out = ['|14Runners currently on the grid:'];
        foreach ($rows as $r) {
            $out[] = sprintf('|08  [%2d] |15%-16s |08%-10s %s', $r['level'], $r['name'], $r['archetype'], $r['title']);
        }
        $out[] = '|08  ' . count($rows) . ' online.';
        return $out;
    }

    private static function cmd_time(array $p): array
    {
        return ['|08It is ' . date('H:i') . ' server time. Night City has no clocks that matter.'];
    }

    private static function cmd_feed(array $p): array
    {
        $out = ['|14Word on the street:'];
        foreach (Db::all("SELECT detail, created_at FROM mud_events WHERE type IN ('kill','death','create','boss','quest') AND detail<>'' ORDER BY id DESC LIMIT 12") as $e) {
            $out[] = '|08  ' . date('H:i', strtotime($e['created_at'])) . '  |07' . $e['detail'];
        }
        return $out;
    }

    /* ---- items on the ground / inventory --------------------------- */

    private static function findItem(array $list, string $kw): ?array
    {
        $kw = strtolower(trim($kw));
        if ($kw === '') {
            return null;
        }
        foreach ($list as $i) {
            $t = $i['tpl'];
            if (str_contains(strtolower($t['keywords'] . ' ' . $t['name']), $kw)) {
                return $i;
            }
        }
        return null;
    }

    private static function cmd_get(array $p, array $args): array
    {
        $what = strtolower(implode(' ', $args));
        if ($what === '' || $what === 'all') {
            $room = World::items('room', (int) $p['room_id']);
            if (!$room) {
                return ['|08Nothing here to grab.'];
            }
            $out = [];
            $limit = Player::maxCarry($p);
            foreach ($room as $i) {
                if (Player::carryWeight((int) $p['id']) + (float) $i['tpl']['weight'] > $limit) {
                    $out[] = '|08You cannot carry any more.';
                    break;
                }
                if ($i['tpl']['type'] === 'currency') {
                    $val = (int) ($i['tpl']['value'] ?: 1);
                    Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$val, $p['id']]);
                    World::destroyItem((int) $i['id']);
                    $out[] = "|14You pick up ¥$val.";
                    continue;
                }
                World::moveItem((int) $i['id'], 'player', (int) $p['id']);
                $out[] = '|07You take ' . $i['tpl']['name'] . '.';
            }
            return $out;
        }
        $i = self::findItem(World::items('room', (int) $p['room_id']), $what);
        if (!$i) {
            return ['|08You see no "' . $what . '" here.'];
        }
        if ($i['tpl']['type'] === 'currency') {
            $val = (int) ($i['tpl']['value'] ?: 1);
            Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$val, $p['id']]);
            World::destroyItem((int) $i['id']);
            self::sfx('coin');
            return ["|14You pick up ¥$val."];
        }
        if (Player::carryWeight((int) $p['id']) + (float) $i['tpl']['weight'] > Player::maxCarry($p)) {
            return ['|08That is too heavy to add to your load.'];
        }
        World::moveItem((int) $i['id'], 'player', (int) $p['id']);
        self::sfx('pickup');
        return ['|07You take ' . $i['tpl']['name'] . '.'];
    }

    private static function cmd_drop(array $p, array $args): array
    {
        $i = self::findItem(Player::inventory((int) $p['id']), implode(' ', $args));
        if (!$i) {
            return ['|08You are not carrying that.'];
        }
        World::moveItem((int) $i['id'], 'room', (int) $p['room_id']);
        return ['|07You drop ' . $i['tpl']['name'] . '.'];
    }

    private static function cmd_put(array $p, array $args): array
    {
        $raw = strtolower(implode(' ', $args));
        if (!str_contains($raw, ' in ')) {
            return ['|08Syntax: put <item> in <container>'];
        }
        [$a, $b] = array_map('trim', explode(' in ', $raw, 2));
        $item = self::findItem(Player::inventory((int) $p['id']), $a);
        $cont = self::findItem(array_merge(Player::inventory((int) $p['id']), World::items('room', (int) $p['room_id'])), $b);
        if (!$item || !$cont) {
            return ['|08You need the item and the container both handy.'];
        }
        if ($cont['tpl']['type'] !== 'container') {
            return ['|08' . ucfirst($cont['tpl']['name']) . ' will not hold anything.'];
        }
        World::moveItem((int) $item['id'], 'container', (int) $cont['id'], (int) $cont['id']);
        return ['|07You put ' . $item['tpl']['name'] . ' in ' . $cont['tpl']['name'] . '.'];
    }

    private static function cmd_give(array $p, array $args): array
    {
        $raw = strtolower(implode(' ', $args));
        if (!str_contains($raw, ' to ')) {
            return ['|08Syntax: give <item> to <who>'];
        }
        [$a, $b] = array_map('trim', explode(' to ', $raw, 2));
        $item = self::findItem(Player::inventory((int) $p['id']), $a);
        if (!$item) {
            return ['|08You do not have that.'];
        }
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if (str_contains(strtolower($mi['tpl']['keywords']), $b)) {
                World::moveItem((int) $item['id'], 'mob', (int) $mi['id']);
                return ['|07You hand ' . $item['tpl']['name'] . ' to ' . $mi['tpl']['name'] . '.',
                        '|08' . ucfirst($mi['tpl']['name']) . ' nods.'];
            }
        }
        foreach (World::players((int) $p['room_id'], (int) $p['id']) as $op) {
            if (str_contains(strtolower($op['name']), $b)) {
                World::moveItem((int) $item['id'], 'player', (int) $op['id']);
                return ['|13You give ' . $item['tpl']['name'] . ' to ' . $op['name'] . '.'];
            }
        }
        return ['|08No one like that here.'];
    }

    /* ---- wear / wield / implant / remove -------------------------- */

    private static function cmd_wear(array $p, array $args): array { return self::doEquip($p, implode(' ', $args), 'wear'); }
    private static function cmd_wield(array $p, array $args): array { return self::doEquip($p, implode(' ', $args), 'wield'); }
    private static function cmd_hold(array $p, array $args): array { return self::doEquip($p, implode(' ', $args), 'held'); }
    private static function cmd_implant(array $p, array $args): array { return self::doEquip($p, implode(' ', $args), 'implant'); }

    private static function doEquip(array $p, string $kw, string $how): array
    {
        $i = self::findItem(Player::inventory((int) $p['id']), $kw);
        if (!$i) {
            return ['|08You are not carrying that.'];
        }
        $t = $i['tpl'];
        $slot = $t['slot'];
        if ($how === 'wield' && !in_array($t['type'], ['weapon'], true)) {
            return ['|08You cannot wield ' . $t['name'] . '.'];
        }
        if ($how === 'implant' && !str_starts_with($slot, 'implant_')) {
            return ['|08' . ucfirst($t['name']) . ' is not chrome you can install.'];
        }
        if ($how === 'wear' && (!$slot || in_array($slot, ['wield', 'held'], true) || str_starts_with($slot, 'implant_'))) {
            return ['|08You cannot wear that.'];
        }
        if ($slot === '') {
            $slot = $how === 'wield' ? 'wield' : ($how === 'hold' ? 'held' : '');
        }
        if ($slot === '') {
            return ['|08No sensible way to equip that.'];
        }
        if ((int) $t['level_req'] > (int) $p['level']) {
            return ['|09You need to be level ' . $t['level_req'] . ' to use ' . $t['name'] . '.'];
        }
        if (str_starts_with($slot, 'implant_')) {
            Db::q('UPDATE mud_players SET energy = GREATEST(0, energy - 3) WHERE id = ?', [$p['id']]);
        }
        $prev = Player::equipmentSlot((int) $p['id'], $slot);
        Player::equip((int) $p['id'], (int) $i['id'], $slot);
        $verb = str_starts_with($slot, 'implant_') ? 'jack in and install' : ($slot === 'wield' ? 'wield' : ($slot === 'held' ? 'hold' : 'put on'));
        self::sfx('equip');
        $out = ["|10You $verb " . $t['name'] . '.'];
        if ($prev) {
            $out[] = '|08(you stop using ' . $prev['tpl']['name'] . ')';
        }
        if (str_starts_with($slot, 'implant_')) {
            $out[] = '|08Chrome hums. Your nervous system files a complaint, then accepts it.';
        }
        return $out;
    }

    private static function cmd_remove(array $p, array $args): array
    {
        $kw = strtolower(implode(' ', $args));
        foreach (Player::equipment((int) $p['id']) as $eq) {
            if (str_contains(strtolower($eq['tpl']['keywords'] . ' ' . $eq['tpl']['name']), $kw)) {
                if (str_starts_with($eq['slot'], 'implant_')) {
                    return ['|08Ripping chrome needs a ripperdoc. Find one and pay up.'];
                }
                Player::unequip((int) $p['id'], $eq['slot']);
                return ['|07You remove ' . $eq['tpl']['name'] . '.'];
            }
        }
        return ['|08You are not using that.'];
    }

    /* ---- consumables -------------------------------------------- */

    private static function cmd_eat(array $p, array $args): array { return self::consume($p, implode(' ', $args), 'eat'); }
    private static function cmd_drink(array $p, array $args): array { return self::consume($p, implode(' ', $args), 'drink'); }
    private static function cmd_inject(array $p, array $args): array { return self::consume($p, implode(' ', $args), 'inject'); }
    private static function cmd_use(array $p, array $args, string $raw): array
    {
        $i = self::findItem(array_merge(Player::inventory((int) $p['id']), Player::equipment((int) $p['id'])), implode(' ', $args));
        if (!$i) {
            // maybe a room feature to hack/use
            return self::cmd_hack($p, $args, $raw);
        }
        return self::consume($p, implode(' ', $args), 'use');
    }

    private static function consume(array $p, string $kw, string $how): array
    {
        $i = self::findItem(array_merge(Player::inventory((int) $p['id']), Player::equipment((int) $p['id'])), $kw);
        if (!$i) {
            return ['|08You do not have that.'];
        }
        $t = $i['tpl'];
        $eff = $t['effect'] ?: [];
        $out = [];
        self::sfx($t['type'] === 'drink' ? 'drink' : ($t['type'] === 'food' ? 'eat' : 'equip'));
        $consumed = in_array($t['type'], ['food', 'drink', 'drug'], true) || ($t['charges'] > 0 && $i['charges_left'] === -1 ? false : $t['charges'] > 0);

        if (isset($eff['heal'])) {
            Db::q('UPDATE mud_players SET hp = LEAST(hp + ?, max_hp) WHERE id = ?', [(int) $eff['heal'], $p['id']]);
            $out[] = '|10You feel ' . (int) $eff['heal'] . ' HP better.';
        }
        if (isset($eff['energy'])) {
            Db::q('UPDATE mud_players SET energy = LEAST(energy + ?, max_energy) WHERE id = ?', [(int) $eff['energy'], $p['id']]);
            $out[] = '|14Your heat sink cools by ' . (int) $eff['energy'] . '.';
        }
        if (isset($eff['food'])) {
            Db::q('UPDATE mud_players SET hunger = LEAST(100, hunger + ?) WHERE id = ?', [(int) $eff['food'], $p['id']]);
            $out[] = '|07You eat ' . $t['name'] . '. ' . ($eff['food'] >= 30 ? 'That actually hits the spot.' : 'It is food, technically.');
        }
        if (isset($eff['drink'])) {
            Db::q('UPDATE mud_players SET thirst = LEAST(100, thirst + ?) WHERE id = ?', [(int) $eff['drink'], $p['id']]);
            $out[] = '|07You drink ' . $t['name'] . '.';
        }
        if (isset($eff['buff'])) {
            $b = $eff['buff'];
            Player::addEffect((int) $p['id'], $b['name'], $b['mods'] ?? [], (int) $b['secs'], (int) ($b['dmg'] ?? 0), $t['name']);
            $out[] = '|11' . ($b['msg'] ?? ('You feel ' . $b['name'] . ' kick in.')) . '  (' . $b['secs'] . 's)';
        }
        if (!$out) {
            $out[] = '|08Nothing much happens.';
        }

        if ($t['charges'] > 0) {
            $left = ($i['charges_left'] < 0 ? $t['charges'] : (int) $i['charges_left']) - 1;
            if ($left <= 0) {
                World::destroyItem((int) $i['id']);
                $out[] = '|08(' . $t['name'] . ' is spent)';
            } else {
                Db::q('UPDATE mud_item_instances SET charges_left = ? WHERE id = ?', [$left, $i['id']]);
            }
        } elseif ($consumed) {
            World::destroyItem((int) $i['id']);
        }
        return $out;
    }

    /* ---- combat ------------------------------------------------- */

    private static function cmd_kill(array $p, array $args): array
    {
        $kw = strtolower(implode(' ', $args));
        if ($kw === '' && $p['target_mob']) {
            return Combat::engage((int) $p['id'], (int) $p['target_mob']);
        }
        $target = null;
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if ($mi['state'] === 'dead') {
                continue;
            }
            if (str_contains(strtolower($mi['tpl']['keywords']), $kw)) {
                $target = $mi;
                break;
            }
        }
        if (!$target) {
            return ['|08You do not see "' . $kw . '" here.'];
        }
        $rf = (string) (World::room((int) $p['room_id'])['flags'] ?? '');
        if (str_contains($rf, 'safe') || str_contains($rf, 'nomob')) {
            return ['|08Not here. Too many cameras. Take it outside.'];
        }
        if (str_contains((string) $target['tpl']['behavior'], 'shopkeeper')) {
            return ['|09Attacking the staff? The whole block would come down on you. Don\'t.'];
        }
        Db::q("UPDATE mud_players SET state='fighting', target_mob=?, pos='standing' WHERE id=?", [$target['id'], $p['id']]);
        return array_merge(['|12You go for ' . $target['tpl']['name'] . '!'], Combat::engage((int) $p['id'], (int) $target['id'], true));
    }

    private static function cmd_flee(array $p): array
    {
        if ($p['state'] !== 'fighting') {
            return ['|08You are not fighting anyone.'];
        }
        $st = Player::effectiveStats($p);
        if (random_int(1, 20) + intdiv($st['reflex'], 2) + Player::skill((int) $p['id'], 'athletics') < 11) {
            // failed flee -> free mob hit via engage one round
            return array_merge(['|09You try to run - and stumble!'], Combat::engage((int) $p['id'], (int) $p['target_mob']));
        }
        $exits = array_values(World::exits((int) $p['room_id']));
        $exits = array_filter($exits, static fn ($x) => !$x['locked'] && !$x['hidden']);
        if (!$exits) {
            return ['|09No way out! You are cornered.'];
        }
        $x = $exits[array_rand($exits)];
        Db::q("UPDATE mud_players SET state='idle', target_mob=NULL, room_id=? WHERE id=?", [$x['to_room'], $p['id']]);
        if ($p['target_mob']) {
            Db::q("UPDATE mud_mob_instances SET state='idle', target_player=NULL WHERE id=?", [$p['target_mob']]);
        }
        $p['room_id'] = $x['to_room'];
        Player::visit($p, (int) $x['to_room']);
        Player::trainSkill((int) $p['id'], 'athletics', 2);
        return array_merge(['|11You break and run ' . (World::DIRS[$x['dir']] ?? $x['dir']) . '!', ''], Render::room($p));
    }

    private static function cmd_consider(array $p, array $args): array
    {
        $kw = strtolower(implode(' ', $args));
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if (str_contains(strtolower($mi['tpl']['keywords']), $kw) && $kw !== '') {
                $d = (int) $mi['tpl']['level'] - (int) $p['level'];
                $verdict = $d <= -4 ? '|10Trivial. Barely worth the ammo.'
                    : ($d <= -1 ? '|10You should win this.'
                    : ($d === 0 ? '|11A fair fight. Bring a stim.'
                    : ($d <= 3 ? '|09This could hurt. A lot.'
                    : '|12Suicidal. Walk away.')));
                return array_merge(Render::examineMob($mi), ['', $verdict]);
            }
        }
        return ['|08Consider who?'];
    }

    /* ---- shops ------------------------------------------------- */

    private static function shopHere(array $p): ?array
    {
        return World::shop((int) $p['room_id']);
    }

    private static function cmd_list(array $p): array
    {
        $shop = self::shopHere($p);
        if (!$shop) {
            return ['|08There is nothing for sale here.'];
        }
        $out = ['|14' . $shop['name'], $shop['greeting'] ? '|08"' . $shop['greeting'] . '"' : '', '',
                '|08  ' . str_pad('ITEM', 34) . str_pad('TYPE', 10) . 'PRICE'];
        $out[] = '|08  ' . str_repeat('-', 54);
        foreach (World::shopStock((int) $shop['id']) as $s) {
            $t = $s['tpl'];
            $price = $s['price_override'] ?? (int) ceil($t['value'] * (float) $shop['sell_markup']);
            $qty = (int) $s['qty'] < 0 ? '' : ' (x' . $s['qty'] . ')';
            $out[] = sprintf('|07  %-34s |08%-10s |14¥%d%s', mb_substr($t['name'], 0, 33), $t['type'], $price, $qty);
        }
        $out[] = '';
        $out[] = '|08  buy <item>   ·   sell <item>   ·   value <item>';
        return $out;
    }

    private static function cmd_buy(array $p, array $args): array
    {
        $shop = self::shopHere($p);
        if (!$shop) {
            return ['|08No shop here.'];
        }
        $kw = strtolower(implode(' ', $args));
        foreach (World::shopStock((int) $shop['id']) as $s) {
            $t = $s['tpl'];
            if (!str_contains(strtolower($t['keywords'] . ' ' . $t['name']), $kw) || $kw === '') {
                continue;
            }
            $price = (int) ($s['price_override'] ?? ceil($t['value'] * (float) $shop['sell_markup']));
            if ((int) $p['money'] < $price) {
                return ['|09You are ' . ($price - (int) $p['money']) . ' eddies short.'];
            }
            if (Player::carryWeight((int) $p['id']) + (float) $t['weight'] > Player::maxCarry($p)) {
                return ['|08You cannot carry that.'];
            }
            Db::q('UPDATE mud_players SET money = money - ? WHERE id = ?', [$price, $p['id']]);
            World::spawnItem((int) $t['vnum'], 'player', (int) $p['id']);
            if ((int) $s['qty'] > 0) {
                Db::q('UPDATE mud_shop_stock SET qty = qty - 1 WHERE id = ?', [$s['id']]);
            }
            self::sfx('buy');
            return ["|10You buy " . $t['name'] . " for ¥$price."];
        }
        return ['|08They do not sell "' . $kw . '".'];
    }

    private static function cmd_sell(array $p, array $args): array
    {
        $shop = self::shopHere($p);
        if (!$shop) {
            return ['|08No one here is buying.'];
        }
        $i = self::findItem(Player::inventory((int) $p['id']), implode(' ', $args));
        if (!$i) {
            return ['|08You are not carrying that.'];
        }
        $t = $i['tpl'];
        $buys = array_filter(array_map('trim', explode(',', $shop['buy_types'])));
        if ($buys && !in_array($t['type'], $buys, true) && $shop['buy_types'] !== '*') {
            return ['|08"I don\'t deal in ' . $t['type'] . '."'];
        }
        if (str_contains($t['flags'], 'quest') || str_contains($t['flags'], 'notrade')) {
            return ['|08"That? Not touching it."'];
        }
        $price = max(1, (int) floor($t['value'] * (float) $shop['buy_markdown'] * ((int) $i['condition'] / 100)));
        Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$price, $p['id']]);
        World::destroyItem((int) $i['id']);
        self::sfx('sell');
        return ["|10You sell " . $t['name'] . " for ¥$price."];
    }

    private static function cmd_value(array $p, array $args): array
    {
        $shop = self::shopHere($p);
        $i = self::findItem(Player::inventory((int) $p['id']), implode(' ', $args));
        if (!$i) {
            return ['|08You do not have that.'];
        }
        $t = $i['tpl'];
        if ($shop) {
            $price = max(1, (int) floor($t['value'] * (float) $shop['buy_markdown']));
            return ['|08"I could give you about ¥' . $price . ' for ' . $t['name'] . '."'];
        }
        return ['|08On the street ' . $t['name'] . ' might fetch ¥' . (int) floor($t['value'] * 0.4) . '.'];
    }

    /* ---- talk / say / emote ---------------------------------- */

    private static function cmd_say(array $p, array $args, string $raw): array
    {
        $msg = trim(substr($raw, strpos($raw, ' ') ?: strlen($raw)));
        if ($msg === '') {
            return ['|08Say what?'];
        }
        foreach (World::players((int) $p['room_id'], (int) $p['id']) as $op) {
            Tick::queue((int) $op['id'], ['|13' . $p['name'] . ' says: |07' . $msg]);
        }
        return ['|07You say: ' . $msg];
    }

    private static function cmd_emote(array $p, array $args, string $raw): array
    {
        $msg = trim(substr($raw, strpos($raw, ' ') ?: strlen($raw)));
        if ($msg === '') {
            return ['|08Emote what?'];
        }
        foreach (World::players((int) $p['room_id'], (int) $p['id']) as $op) {
            Tick::queue((int) $op['id'], ['|13' . $p['name'] . ' ' . $msg]);
        }
        return ['|13' . $p['name'] . ' ' . $msg];
    }

    private static function cmd_talk(array $p, array $args, string $raw): array
    {
        $raw = strtolower($raw);
        $topic = null;
        if (preg_match('/\babout\s+(.+)$/', $raw, $m)) {
            $topic = trim($m[1]);
        }
        $who = trim(preg_replace('/^(talk|ask|greet)\s+|\babout\s+.+$/', '', $raw));
        $mi = null;
        foreach (World::mobs((int) $p['room_id']) as $cand) {
            if ($who === '' || str_contains(strtolower($cand['tpl']['keywords']), $who)) {
                $mi = $cand;
                break;
            }
        }
        if (!$mi) {
            return ['|08There is no one here to talk to.'];
        }
        $t = $mi['tpl'];
        $dlg = $t['dialogue'] ?: [];
        $out = [];
        // dialogue lines carry their own quotes inconsistently; normalise to one pair
        $say = static fn (string $s): string => '"' . trim($s, '"') . '"';

        // fixer / questgiver hooks
        if (str_contains($t['behavior'], 'questgiver')) {
            $turn = Quests::turnIn((int) $p['id'], (int) $t['vnum']);
            if ($turn) {
                return array_merge(['|11' . ucfirst($t['name']) . ': ' . $say('Good work.')], $turn);
            }
            $avail = array_filter(Quests::forGiver((int) $t['vnum']), function ($q) use ($p) {
                $s = Quests::status((int) $p['id'], (int) $q['id']);
                return (!$s || $s['status'] === 'failed') && (int) $p['level'] >= (int) $q['level_req'];
            });
            if ($avail && ($topic === null || in_array($topic, ['job', 'jobs', 'work', 'gig'], true))) {
                $out[] = '|11' . ucfirst($t['name']) . ': ' . $say($dlg['job'] ?? 'Got something for you.');
                foreach ($avail as $q) {
                    $out[] = "|08  * {$q['name']} |07- {$q['summary']}  |08(reward: ¥{$q['reward_money']})";
                }
                $out[] = '|08  Type |15accept <name>|08 to take one.';
                return $out;
            }
        }

        if ($topic !== null && isset($dlg['topics'][$topic])) {
            return ['|07' . ucfirst($t['name']) . ': ' . $say($dlg['topics'][$topic])];
        }
        $greet = $say($dlg['greet'] ?? 'What.');
        return ['|07' . ucfirst($t['name']) . ': ' . $greet .
                (isset($dlg['topics']) ? '  |08(ask about: ' . implode(', ', array_keys($dlg['topics'])) . ')' : '')];
    }

    private static function cmd_accept(array $p, array $args): array
    {
        $name = strtolower(implode(' ', $args));
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            foreach (Quests::forGiver((int) $mi['tpl']['vnum']) as $q) {
                if (str_contains(strtolower($q['name']), $name) && $name !== '') {
                    $r = Quests::accept((int) $p['id'], $q);
                    if ($r && str_contains($r[0], 'ACCEPTED')) {
                        self::sfx('quest');
                    }
                    return $r;
                }
            }
        }
        return ['|08No such job on offer here.'];
    }

    private static function cmd_quests(array $p): array
    {
        $q = Quests::active((int) $p['id']);
        if (!$q) {
            return ['|08No active jobs. Find a fixer and |15talk|08 to them.'];
        }
        $out = ['|14Active jobs:'];
        foreach ($q as $j) {
            $out[] = sprintf('|11  %s  |08[%d/%d]', $j['name'], $j['progress'], $j['goal_count']);
            $out[] = '|07    ' . $j['summary'];
        }
        return $out;
    }

    /* ---- rob ------------------------------------------------- */

    private static function cmd_rob(array $p, array $args): array
    {
        $kw = strtolower(implode(' ', $args));
        $mi = null;
        foreach (World::mobs((int) $p['room_id']) as $c) {
            if (str_contains(strtolower($c['tpl']['keywords']), $kw) && $kw !== '') {
                $mi = $c;
                break;
            }
        }
        if (!$mi) {
            return ['|08Rob who?'];
        }
        $t = $mi['tpl'];
        if (str_contains($t['behavior'], 'shopkeeper') || str_contains($t['behavior'], 'questgiver')
            || in_array($t['faction'], ['police', 'fixer'], true)) {
            return ['|09Bad idea. Very bad idea. That is not someone you want owing you a grudge.'];
        }
        $st = Player::effectiveStats($p);
        $roll = random_int(1, 20) + intdiv($st['cool'], 2) + Player::skill((int) $p['id'], 'stealth');
        Player::trainSkill((int) $p['id'], 'stealth', 3);
        if ($roll < 10 + (int) $t['level']) {
            Db::q("UPDATE mud_mob_instances SET state='fighting', target_player=? WHERE id=?", [$p['id'], $mi['id']]);
            Db::q("UPDATE mud_players SET state='fighting', target_mob=? WHERE id=?", [$mi['id'], $p['id']]);
            return array_merge(['|09' . ucfirst($t['name']) . ' catches your hand in their pocket!'],
                Combat::engage((int) $p['id'], (int) $mi['id']));
        }
        $take = random_int((int) $t['money_min'], max((int) $t['money_min'], (int) $t['money_max']));
        if ($take > 0) {
            Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$take, $p['id']]);
        }
        // chance to lift an item from the loot table
        $extra = [];
        foreach (($t['loot_table'] ?? []) as $d) {
            if (random_int(1, 100) <= (int) ($d['chance'] ?? 0) / 2) {
                $iid = World::spawnItem((int) $d['vnum'], 'player', (int) $p['id']);
                if ($iid) {
                    $extra[] = '|08You also lift ' . World::itemInstance($iid)['tpl']['name'] . '.';
                }
                break;
            }
        }
        Quests::progress((int) $p['id'], 'rob', explode(' ', $t['keywords'])[0]);
        self::sfx('coin');
        return array_merge(["|10You pick " . $t['name'] . "'s pocket for ¥$take."], $extra);
    }

    /* ---- hack ---------------------------------------------- */

    private static function cmd_hack(array $p, array $args, string $raw): array
    {
        $kw = strtolower(implode(' ', $args));
        if ($kw === '') {
            return ['|08Hack what? (a door, a terminal, a vending machine, an ATM, a camera - or an enemy in a fight)'];
        }
        $st = Player::effectiveStats($p);
        $skill = Player::skill((int) $p['id'], 'hacking');
        $power = intdiv($st['intel'] + $st['tech'], 2) + $skill;
        $hasDeck = false;
        foreach (Player::equipment((int) $p['id']) as $eq) {
            if ($eq['tpl']['type'] === 'computer') {
                $hasDeck = true;
                $power += (int) ($eq['tpl']['stat_mods']['tech'] ?? 0) + 2;
            }
        }
        if (!$hasDeck) {
            $power -= 3;
        }
        if ((int) $p['energy'] < 3) {
            return ['|09Your deck is overheating. Rest and let the heat sink cool.'];
        }
        Db::q('UPDATE mud_players SET energy = GREATEST(0, energy - 3) WHERE id = ?', [$p['id']]);
        self::sfx('hack');

        // 1) a locked/electronic exit
        foreach (World::exits((int) $p['room_id']) as $dir => $x) {
            if ($x['hack_dc'] > 0 && ($x['keyword'] === '' || str_contains($kw, strtolower($x['keyword'])) || in_array($kw, ['door', 'lock', 'panel'], true))) {
                $roll = random_int(1, 20) + $power;
                Player::trainSkill((int) $p['id'], 'hacking', 5);
                if ($roll >= 10 + (int) $x['hack_dc']) {
                    Db::q('UPDATE mud_exits SET locked = 0 WHERE id = ?', [$x['id']]);
                    self::sfx('hackok', 'door');
                    return ['|10ICE peels away. The lock ' . (World::DIRS[$dir] ?? $dir) . ' clicks open.'];
                }
                if ($roll < (int) $x['hack_dc']) {
                    self::traceHeat($p, 'The lock trips an alarm.');
                    return ['|09Countermeasure! An alarm chirps somewhere. (a patrol may be inbound)'];
                }
                return ['|08The lock rejects your intrusion. Try again.'];
            }
        }

        // 2) a mob you are fighting -> debuff/stun
        if ($p['state'] === 'fighting' && $p['target_mob']) {
            $mi = World::mobInstance((int) $p['target_mob']);
            if ($mi && (in_array($kw, ['target', 'enemy', 'them', 'it'], true) || str_contains(strtolower($mi['tpl']['keywords']), $kw))) {
                $roll = random_int(1, 20) + $power;
                Player::trainSkill((int) $p['id'], 'hacking', 4);
                if ($roll >= 12 + (int) $mi['tpl']['level']) {
                    $dmg = random_int(4, 10) + intdiv($power, 2);
                    Db::q('UPDATE mud_mob_instances SET hp = hp - ? WHERE id = ?', [$dmg, $mi['id']]);
                    Player::addEffect((int) $p['id'], 'Uploaded Malware', [], 20, 3, 'hack');
                    return ["|10You slam a glitch protocol into {$mi['tpl']['name']}'s cyberware - |15$dmg|10 dmg, and your next hits bite harder."];
                }
                return ['|08Their onboard ICE holds. No effect.'];
            }
        }

        // 3) room features by keyword
        $feat = [
            'vending'  => ['dc' => 8,  'win' => 'A snack drops into the tray, gratis.', 'loot' => [3101, 3102, 3103]],
            'machine'  => ['dc' => 8,  'win' => 'The machine coughs up its stock.',      'loot' => [3101, 3103]],
            'atm'      => ['dc' => 16, 'win' => 'money',                                  'trace' => true],
            'terminal' => ['dc' => 12, 'win' => 'You pull some loose scrip and a data shard.', 'loot' => [3050, 3901], 'cash' => [20, 90]],
            'camera'   => ['dc' => 10, 'win' => 'You loop the camera feed. NCPD is blind here for a while.'],
            'panel'    => ['dc' => 12, 'win' => 'You reroute the panel. Something useful spits out.', 'loot' => [3901]],
        ];
        foreach ($feat as $word => $f) {
            if (str_contains($kw, $word)) {
                $roll = random_int(1, 20) + $power;
                Player::trainSkill((int) $p['id'], 'hacking', 4);
                if ($roll >= 10 + $f['dc']) {
                    $out = ['|10Access granted.'];
                    if (($f['win'] ?? '') === 'money' || isset($f['cash'])) {
                        $cash = isset($f['cash']) ? random_int($f['cash'][0], $f['cash'][1]) : random_int(50, 250);
                        Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$cash, $p['id']]);
                        $out[] = "|14You siphon ¥$cash.";
                    }
                    if (is_string($f['win'] ?? null) && $f['win'] !== 'money') {
                        $out[] = '|07' . $f['win'];
                    }
                    foreach (($f['loot'] ?? []) as $vn) {
                        if (random_int(1, 100) <= 60) {
                            $iid = World::spawnItem($vn, 'room', (int) $p['room_id']);
                            if ($iid) {
                                $out[] = '|08...' . World::itemInstance($iid)['tpl']['name'] . ' clatters out.';
                            }
                        }
                    }
                    if (!empty($f['trace'])) {
                        self::traceHeat($p, 'That ATM logs everything.');
                    }
                    return $out;
                }
                if (!empty($f['trace']) || $roll < $f['dc'] - 2) {
                    self::traceHeat($p, 'A trace locks onto you.');
                    return ['|09The system fights back and pings your location. Move!'];
                }
                return ['|08Rejected. The countermeasures were tougher than they looked.'];
            }
        }
        return ['|08You cannot find a way into that.'];
    }

    private static function traceHeat(array $p, string $why): void
    {
        Player::addEffect((int) $p['id'], 'Traced', [], 90, 0, 'trace');
        Player::addWanted((int) $p['id'], 8);
        self::sfx('trace');
        World::event((int) $p['id'], 'trace', $why);
        // a NCPD patrol homes in on the trace: wake / relocate a nearby idle cop
        $cop = Db::one(
            "SELECT mi.id FROM mud_mob_instances mi
             JOIN mud_mob_templates mt ON mt.id = mi.template_id
             JOIN mud_rooms r ON r.id = mi.room_id
             WHERE mt.faction = 'police' AND mi.state = 'idle'
               AND r.zone_id = (SELECT zone_id FROM mud_rooms WHERE id = ?)
             ORDER BY RAND() LIMIT 1",
            [$p['room_id']]
        );
        if ($cop) {
            Db::q("UPDATE mud_mob_instances SET room_id = ?, last_act_at = NOW() WHERE id = ?", [$p['room_id'], $cop['id']]);
            Tick::queue((int) $p['id'], ['|09Sirens. An NCPD patrol rounds the corner, scanning.']);
        }
    }

    /* ---- positions -------------------------------------------- */

    private static function cmd_rest(array $p): array
    {
        if ($p['state'] === 'fighting') {
            return ['|12Not while someone is trying to kill you.'];
        }
        Db::q("UPDATE mud_players SET pos='resting' WHERE id=?", [$p['id']]);
        return ['|07You find a wall to put your back against and rest. HP and heat recover faster now.'];
    }
    private static function cmd_sleep(array $p): array
    {
        if ($p['state'] === 'fighting') {
            return ['|12You cannot sleep in a firefight.'];
        }
        Db::q("UPDATE mud_players SET pos='sleeping' WHERE id=?", [$p['id']]);
        return ['|07You crash out. (recovering fast - but you are helpless. |15wake|07 to get up)'];
    }
    private static function cmd_wake(array $p): array
    {
        Db::q("UPDATE mud_players SET pos='standing' WHERE id=?", [$p['id']]);
        return ['|07You get up.'];
    }
    private static function cmd_sit(array $p): array
    {
        Db::q("UPDATE mud_players SET pos='sitting' WHERE id=?", [$p['id']]);
        return ['|07You sit down.'];
    }
    private static function cmd_stand(array $p): array
    {
        Db::q("UPDATE mud_players SET pos='standing' WHERE id=?", [$p['id']]);
        return ['|07You stand up.'];
    }

    /* ---- recall / bank ------------------------------------- */

    private static function cmd_recall(array $p): array
    {
        if ($p['state'] === 'fighting') {
            return ['|12You cannot jack out mid-fight.'];
        }
        Db::q('UPDATE mud_players SET room_id = respawn_room_id WHERE id = ?', [$p['id']]);
        $p['room_id'] = (int) $p['respawn_room_id'];
        Player::visit($p, (int) $p['room_id']);
        return array_merge(['|11You duck into the nearest safehouse and catch your breath.', ''], Render::room($p));
    }

    private static function cmd_deposit(array $p, array $args): array
    {
        if (!self::bankHere($p)) {
            return ['|08No terminal here to bank with.'];
        }
        $n = (int) ($args[0] ?? 0);
        $n = $args && strtolower($args[0]) === 'all' ? (int) $p['money'] : $n;
        if ($n <= 0 || $n > (int) $p['money']) {
            return ['|08Deposit how much?'];
        }
        Db::q('UPDATE mud_players SET money = money - ?, bank = bank + ? WHERE id = ?', [$n, $n, $p['id']]);
        return ["|10Deposited ¥$n. Bank balance: ¥" . ((int) $p['bank'] + $n)];
    }
    private static function cmd_withdraw(array $p, array $args): array
    {
        if (!self::bankHere($p)) {
            return ['|08No terminal here.'];
        }
        $n = $args && strtolower($args[0]) === 'all' ? (int) $p['bank'] : (int) ($args[0] ?? 0);
        if ($n <= 0 || $n > (int) $p['bank']) {
            return ['|08Withdraw how much?'];
        }
        Db::q('UPDATE mud_players SET money = money + ?, bank = bank - ? WHERE id = ?', [$n, $n, $p['id']]);
        return ["|10Withdrew ¥$n."];
    }
    private static function bankHere(array $p): bool
    {
        $room = World::room((int) $p['room_id']);
        return $room && str_contains((string) $room['flags'], 'bank');
    }

    /* ---- spend stat points -------------------------------- */

    private static function cmd_spend(array $p, array $args): array
    {
        $stat = strtolower($args[0] ?? '');
        if (!in_array($stat, Player::STATS, true)) {
            return ['|08Spend on: ' . implode(', ', Player::STATS) . '   (you have ' . $p['unspent_points'] . ' points)'];
        }
        if ((int) $p['unspent_points'] < 1) {
            return ['|08No stat points to spend. Level up first.'];
        }
        $s = json_decode($p['stats'], true);
        $s[$stat] = (int) ($s[$stat] ?? 4) + 1;
        Db::q('UPDATE mud_players SET stats = ?, unspent_points = unspent_points - 1 WHERE id = ?', [json_encode($s), $p['id']]);
        return ["|10$stat raised to {$s[$stat]}.  |08(" . ((int) $p['unspent_points'] - 1) . ' points left)'];
    }

    /* ---- train a skill at a trainer NPC ---------------- */

    private static function trainerHere(array $p): ?array
    {
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if (str_starts_with((string) $mi['tpl']['behavior'], 'trainer:') || str_contains((string) $mi['tpl']['behavior'], 'trainer:')) {
                return $mi;
            }
        }
        return null;
    }

    private static function cmd_train(array $p, array $args): array
    {
        $mi = self::trainerHere($p);
        if (!$mi) {
            return ['|08No one here trains. Find a trainer - the Motorpool out past the wall, a street coach in Kabuki, others about.'];
        }
        $can = [];
        if (preg_match('/trainer:([a-z,]+)/', (string) $mi['tpl']['behavior'], $m)) {
            $can = array_filter(explode(',', $m[1]));
        }
        $skill = strtolower($args[0] ?? '');
        if ($skill === '' || !in_array($skill, $can, true)) {
            return ['|07' . ucfirst($mi['tpl']['name']) . ' teaches: |15' . implode('|07, |15', $can)
                . '|07.', '|08Syntax: |15train <skill>|08.  Each level costs eddies and gets steeper.'];
        }
        $lvl = Player::skill((int) $p['id'], $skill);
        if ($lvl >= 20) {
            return ['|08"There is nothing more I can show you in ' . $skill . '."'];
        }
        $cost = 120 + $lvl * $lvl * 40;
        if ((int) $p['money'] < $cost) {
            return ["|09That lesson costs ¥$cost. You are ¥" . ($cost - (int) $p['money']) . ' short.'];
        }
        Db::q('UPDATE mud_players SET money = money - ? WHERE id = ?', [$cost, $p['id']]);
        Db::q(
            'INSERT INTO mud_player_skills (player_id, skill, level, xp) VALUES (?,?,?,0)
             ON DUPLICATE KEY UPDATE level = ?',
            [(int) $p['id'], $skill, $lvl + 1, $lvl + 1]
        );
        self::sfx('levelup');
        return ["|10You drill $skill until it is instinct.  |15$skill " . ($lvl + 1) . "|10.  |08(-¥$cost)"];
    }

    /* ---- ripperdoc: pull chrome back out --------------- */

    private static function cmd_uninstall(array $p, array $args): array
    {
        $doc = null;
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if (str_contains((string) $mi['tpl']['behavior'], 'ripperdoc')) {
                $doc = $mi;
                break;
            }
        }
        $selfKit = self::findItem(Player::inventory((int) $p['id']), 'ripperkit');
        if (!$doc && !$selfKit) {
            return ['|08You need a ripperdoc, or a ripperdoc field kit in your pack, to pull chrome safely.'];
        }
        $kw = strtolower(implode(' ', $args));
        $target = null;
        foreach (Player::equipment((int) $p['id']) as $eq) {
            if (str_starts_with($eq['slot'], 'implant_')
                && str_contains(strtolower($eq['tpl']['keywords'] . ' ' . $eq['tpl']['name']), $kw) && $kw !== '') {
                $target = $eq;
                break;
            }
        }
        if (!$target) {
            $worn = array_values(array_filter(Player::equipment((int) $p['id']), static fn ($e) => str_starts_with($e['slot'], 'implant_')));
            if (!$worn) {
                return ['|08You have no chrome installed.'];
            }
            return array_merge(['|07Installed chrome:'], array_map(static fn ($e) => '|08  ' . $e['tpl']['name'] . ' |08<' . substr($e['slot'], 8) . '>', $worn),
                ['|08Syntax: |15uninstall <name>|08.']);
        }
        $fee = $doc ? 200 + (int) $target['tpl']['value'] : 0;   // free-ish with your own kit, but risky
        if ($doc && (int) $p['money'] < $fee) {
            return ["|09The doc wants ¥$fee to take that out clean. You cannot cover it."];
        }
        if ($doc) {
            Db::q('UPDATE mud_players SET money = money - ? WHERE id = ?', [$fee, $p['id']]);
        }
        Player::unequip((int) $p['id'], $target['slot']);
        $ok = $doc || random_int(1, 20) + intdiv(Player::effectiveStats($p)['tech'], 2) + Player::skill((int) $p['id'], 'engineering') >= 12;
        if ($ok) {
            World::moveItem((int) $target['id'], 'player', (int) $p['id']);
            self::sfx('equip');
            return [$doc
                ? "|10The doc pulls the " . self::bare($target['tpl']['name']) . " clean and hands it back.  |08(-¥$fee)"
                : "|10You pull the " . self::bare($target['tpl']['name']) . " yourself. It goes... mostly to plan."];
        }
        World::destroyItem((int) $target['id']);
        Db::q('UPDATE mud_players SET hp = GREATEST(1, hp - ?) WHERE id = ?', [random_int(6, 16), $p['id']]);
        self::sfx('hurt');
        return ['|12The extraction goes wrong. The ' . self::bare($target['tpl']['name']) . ' is scrap and you are bleeding. Should have paid a doc.'];
    }

    /* ---- bribe your NCPD heat away --------------------- */

    private static function cmd_bribe(array $p): array
    {
        $w = Player::wanted($p);
        if ($w <= 0) {
            return ['|08You are not wanted. Enjoy it while it lasts.'];
        }
        $fixer = null;
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            $d = $mi['tpl']['dialogue'] ?? [];
            if (isset($d['topics']['heat']) || str_contains((string) $mi['tpl']['behavior'], 'fence')) {
                $fixer = $mi;
                break;
            }
        }
        if (!$fixer) {
            return ['|08Nobody here can make a flag disappear. Ask around - a bent fixer works Gomorrah Lane, and Wirehead on the roofs deals in heat too.'];
        }
        $cost = 300 + $w * 30;
        if ((int) $p['money'] < $cost) {
            return ["|09Clearing that much heat costs ¥$cost. You have ¥{$p['money']}. Come back richer or cooler."];
        }
        Db::q('UPDATE mud_players SET money = money - ? WHERE id = ?', [$cost, $p['id']]);
        Player::clearWanted((int) $p['id']);
        World::event((int) $p['id'], 'wanted', "{$p['name']} bought their way off the list.");
        self::sfx('coin');
        return ['|11' . ucfirst($fixer['tpl']['name']) . " makes a call. \"Done. Your face is nobody's problem for a while.\"  |08(-¥$cost)"];
    }

    /* ---- the job board: repeatable procedural bounties - */

    private static function boardHere(array $p): bool
    {
        return str_contains((string) (World::room((int) $p['room_id'])['flags'] ?? ''), 'board');
    }

    /** Three bounties that rotate every ~30 min, stable per board. */
    private static function boardOffers(array $p): array
    {
        $seed = (int) $p['room_id'] * 7919 + intdiv(time(), 1800);
        mt_srand($seed);
        $pool = [
            ['kill', 'rat',      'Vermin cull',      'Something with too many legs is nesting nearby. Thin it out.'],
            ['kill', 'ganger',   'Turf notice',      'A crew is leaning on the wrong people. Push back.'],
            ['kill', 'scav',     'Harvest halted',   'The Scavs are shopping for parts again. Discourage them.'],
            ['kill', 'raffen',   'Road tax refund',  'Raffen Shiv hit a convoy. The clans want the balance settled.'],
            ['kill', 'cop',      'Blue on the ground','Off the record: a patrol needs to not make it back. Deniable.'],
            ['collect', 'scrip', 'Debt collection',  'Shake loose a stack of scrip from whoever is holding.'],
            ['collect', 'scrap', 'Salvage quota',    'A buyer wants cyber-scrap by the armful. No questions.'],
        ];
        $lvl = (int) $p['level'];
        $picks = [];
        $keys = (array) array_rand($pool, 3);
        foreach ($keys as $k) {
            [$type, $target, $name, $desc] = $pool[$k];
            $need = $type === 'kill' ? mt_rand(3, 6) : mt_rand(2, 5);   // mt_rand: seeded, so listing == accept
            $reward = $need * (40 + $lvl * 14) + mt_rand(0, 60);
            $picks[] = compact('type', 'target', 'name', 'desc', 'need', 'reward');
        }
        mt_srand();
        return $picks;
    }

    private static function cmd_board(array $p, array $args): array
    {
        if (!self::boardHere($p)) {
            return ['|08There is no job board here. Try the Afterlife, the Bazaar, the nomad camp, the rooftops.'];
        }
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $active = $d['bounty'] ?? null;
        $sub = strtolower($args[0] ?? '');

        // turn in a finished bounty
        if ($active && ($active['have'] ?? 0) >= $active['need']) {
            $r = (int) $active['reward'];
            Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$r, $p['id']]);
            unset($d['bounty']);
            Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $p['id']]);
            self::sfx('coin', 'quest');
            $xp = Player::grantXp((int) $p['id'], (int) round($r * 0.4));
            return array_merge(["|11BOUNTY PAID: {$active['name']}  |14+¥$r"], $xp, ['|08The board refreshes. Type |15board|08 for new work.']);
        }

        if ($active && $sub !== 'drop') {
            return ["|14Active bounty: |11{$active['name']}  |08[{$active['have']}/{$active['need']}]",
                    '|07  ' . $active['desc'],
                    '|08  Finish it and come back to any board to collect. |15board drop|08 to abandon it.'];
        }
        if ($sub === 'drop' && $active) {
            unset($d['bounty']);
            Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $p['id']]);
            return ['|08Bounty abandoned. The board does not care.'];
        }

        $offers = self::boardOffers($p);
        if (in_array($sub, ['1', '2', '3', 'a', 'b', 'c'], true)) {
            $idx = ctype_digit($sub) ? (int) $sub - 1 : ord($sub) - 97;
            if (!isset($offers[$idx])) {
                return ['|08No such listing.'];
            }
            $o = $offers[$idx];
            $d['bounty'] = ['name' => $o['name'], 'desc' => $o['desc'], 'type' => $o['type'],
                            'target' => $o['target'], 'need' => $o['need'], 'have' => 0, 'reward' => $o['reward']];
            Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $p['id']]);
            self::sfx('quest');
            return ["|11BOUNTY TAKEN: {$o['name']}  |08({$o['type']} {$o['target']} x{$o['need']}, |14¥{$o['reward']}|08)",
                    '|07  ' . $o['desc']];
        }

        $out = ['|14  ' . strtoupper('job board') . '   |08- open bounties, refreshes on the half-hour', ''];
        foreach ($offers as $i => $o) {
            $out[] = sprintf('|08  [|15%d|08] |11%-18s |08%s %s x%d   |14¥%d', $i + 1, $o['name'], $o['type'], $o['target'], $o['need'], $o['reward']);
            $out[] = '|08       ' . $o['desc'];
        }
        $out[] = '';
        $out[] = '|08  |15board <n>|08 to take one. One bounty at a time.';
        return $out;
    }

    /** Called from Combat on a kill - advances a matching active bounty. */
    public static function bountyKill(int $playerId, string $keyword): void
    {
        self::bountyTick($playerId, 'kill', $keyword);
    }

    public static function bountyTick(int $playerId, string $type, string $token): void
    {
        $p = Player::byId($playerId);
        if (!$p) {
            return;
        }
        $d = json_decode($p['data'] ?? '{}', true) ?: [];
        $b = $d['bounty'] ?? null;
        if (!$b || $b['type'] !== $type || ($b['have'] ?? 0) >= $b['need']) {
            return;
        }
        if (!str_contains(strtolower($token), strtolower($b['target'])) && $b['target'] !== '') {
            // loosen: 'cop' matches police keywords, 'ganger' matches gang mob keywords
            $aliases = ['cop' => 'officer|ncpd|maxtac|police', 'ganger' => 'ganger|enforcer|claw|maelstrom|scav|raffen'];
            $rx = $aliases[$b['target']] ?? null;
            if (!$rx || !preg_match('/' . $rx . '/i', $token)) {
                return;
            }
        }
        $b['have'] = (int) $b['have'] + 1;
        $d['bounty'] = $b;
        Db::q('UPDATE mud_players SET data = ? WHERE id = ?', [json_encode($d), $playerId]);
        Mud::sfx('pickup');
    }

    /* ---- scan adjacent rooms -------------------------- */

    private static function cmd_scan(array $p): array
    {
        $out = ['|14You scan the surroundings:'];
        $any = false;
        foreach (World::exits((int) $p['room_id']) as $dir => $x) {
            if ($x['hidden']) {
                continue;
            }
            $there = World::room((int) $x['to_room']);
            if (!$there) {
                continue;
            }
            $mobs = array_values(array_filter(World::mobs((int) $x['to_room']), static fn ($m) => $m['state'] !== 'dead'));
            $label = World::DIRS[$dir] ?? $dir;
            if (!$mobs) {
                $out[] = sprintf('|08  %-9s %s - quiet', $label, $there['name']);
            } else {
                $names = array_slice(array_map(static fn ($m) => $m['tpl']['name'], $mobs), 0, 4);
                $out[] = sprintf('|07  %-9s %s |08- |09%s', $label, $there['name'], implode(', ', $names));
                $any = true;
            }
        }
        if (count($out) === 1) {
            $out[] = '|08  nowhere to scan from here.';
        } elseif (!$any) {
            $out[] = '|08  Nothing moving nearby.';
        }
        return $out;
    }

    /* ---- examine --------------------------------------- */

    private static function cmd_examine(array $p, array $args, string $raw): array
    {
        $kw = strtolower(implode(' ', $args));
        if ($kw === '') {
            return Render::room($p);
        }
        // self / me
        if (in_array($kw, ['me', 'self'], true)) {
            return Render::score($p);
        }
        // a direction
        $dirmap = ['n' => 'n', 's' => 's', 'e' => 'e', 'w' => 'w', 'u' => 'u', 'd' => 'd', 'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w', 'up' => 'u', 'down' => 'd'];
        if (isset($dirmap[$kw])) {
            $x = World::exits((int) $p['room_id'])[$dirmap[$kw]] ?? null;
            if (!$x) {
                return ['|08Nothing that way.'];
            }
            return ['|07' . ($x['descr'] ?: 'You see the way to ' . $x['to_name'] . '.')];
        }
        // items in room / inventory / equipment
        $item = self::findItem(array_merge(
            World::items('room', (int) $p['room_id']),
            Player::inventory((int) $p['id']),
            Player::equipment((int) $p['id'])
        ), $kw);
        if ($item) {
            return Render::examineItem($item['tpl']);
        }
        // mobs
        foreach (World::mobs((int) $p['room_id']) as $mi) {
            if (str_contains(strtolower($mi['tpl']['keywords']), $kw)) {
                return Render::examineMob($mi);
            }
        }
        // other players
        foreach (World::players((int) $p['room_id'], (int) $p['id']) as $op) {
            if (str_contains(strtolower($op['name']), $kw)) {
                $out = ['|13' . $op['name'] . ($op['title'] ? ', ' . $op['title'] : ''),
                    '|08A level ' . $op['level'] . ' ' . $op['archetype'] . '. ' . ((int) $op['hp'] < (int) $op['max_hp'] * 0.4 ? 'Looks hurt.' : 'Looks fine.')];
                $w = Player::equipmentSlot((int) $op['id'], 'wield');
                if ($w) {
                    $out[] = '|08Wielding ' . $w['tpl']['name'] . '.';
                }
                return $out;
            }
        }
        // readable room lore - terminals, graffiti, notes, ads
        $lore = World::roomExtra((int) $p['room_id'], $kw);
        if ($lore !== null) {
            $out = [];
            foreach (Render::wrapText($lore) as $l) {
                $out[] = '|07' . $l;
            }
            return $out;
        }
        return ['|08You see no "' . $kw . '" here.  |08(try looking at the terminals, signs, graffiti...)'];
    }

    /* ---- help --------------------------------------------- */

    private static function cmd_help(array $p, array $args): array
    {
        $topic = strtolower($args[0] ?? '');
        if ($topic === 'combat') {
            return [
                '|14COMBAT',
                '|07  kill <enemy>   start a fight (also: attack, hit, k)',
                '|07  (blank line)   press ENTER to trade another round',
                '|07  flee           try to run - reflex + athletics check',
                '|07  hack <enemy>   during a fight: glitch their chrome for damage + a buff',
                '|07  consider <e>   size someone up before you commit',
                '|08  Below 30% HP the fight pauses so you can flee or |15inject|08 a stim.',
                '|08  Die and Trauma Team revives you at a safehouse, minus 25% of your eddies.',
            ];
        }
        if ($topic === 'hacking') {
            return [
                '|14HACKING',
                '|07  hack <door>       open an electronic lock',
                '|07  hack terminal     pull eddies / data shards',
                '|07  hack vending      free snacks',
                '|07  hack atm          big money, big trace risk (patrol inbound)',
                '|07  hack camera       blind NCPD in this area for a while',
                '|08  Uses INT+TECH+hacking skill and costs heat. A cyberdeck helps a lot.',
            ];
        }
        return [
            '|09  HACKERS-MUD  -  command reference   |08(help combat · help hacking)',
            '|08  ' . str_repeat('-', 60),
            '|07  MOVE     n s e w u d ne nw se sw   ·  enter / exit  ·  go <dir>',
            '|07  SEE      look (l) · look <thing> · examine (x) <thing> · map (m) · scan',
            '|07  SELF     score (sc) · inventory (i) · equipment (eq) · who · feed',
            '|07  ITEMS    get / drop / put <x> in <y> · give <x> to <y>',
            '|07  GEAR     wear · wield · hold · implant · remove · uninstall <chrome> (at a ripperdoc)',
            '|07  USE      use · eat · drink · inject <stim>',
            '|07  FIGHT    kill <e> · flee · consider <e> · hack <e>',
            '|07  SHOP     list · buy <x> · sell <x> · value <x>',
            '|07  TALK     talk <npc> [about <topic>] · say <msg> · emote <msg>',
            '|07  JOBS     talk to a fixer · accept <job> · quests · board (repeatable bounties)',
            '|07  GROW     spend <stat> · train <skill> (at a trainer)',
            '|07  CRIME    rob <npc> · hack atm/terminal/camera · bribe (clear NCPD heat)',
            '|07  BODY     rest · sleep · wake · sit · stand',
            '|07  MISC     recall (home) · deposit/withdraw <n> · time',
            '|08  Look at the terminals, signs and graffiti - most rooms have something to read.',
            '|08  Leave the MUD any time with ESC - your character is saved.',
        ];
    }
}
