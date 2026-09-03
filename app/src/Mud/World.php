<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * Data access for the MUD world - rooms, exits, item & mob instances, shops.
 * Small static cache for templates (they don't change during a session).
 */
final class World
{
    /** @var array<int,array> */
    private static array $itemTpl = [];
    /** @var array<int,array> */
    private static array $mobTpl = [];

    public const DIRS = ['n' => 'north', 's' => 'south', 'e' => 'east', 'w' => 'west',
                         'u' => 'up', 'd' => 'down', 'ne' => 'northeast', 'nw' => 'northwest',
                         'se' => 'southeast', 'sw' => 'southwest', 'in' => 'in', 'out' => 'out'];
    public const OPP = ['n' => 's', 's' => 'n', 'e' => 'w', 'w' => 'e', 'u' => 'd', 'd' => 'u',
                        'ne' => 'sw', 'nw' => 'se', 'se' => 'nw', 'sw' => 'ne', 'in' => 'out', 'out' => 'in'];

    public static function room(int $id): ?array
    {
        return Db::one('SELECT * FROM mud_rooms WHERE id = ?', [$id]);
    }

    public static function roomByVnum(int $vnum): ?array
    {
        return Db::one('SELECT * FROM mud_rooms WHERE vnum = ?', [$vnum]);
    }

    /** @return array<string,array> dir => exit row (+ to room name) */
    public static function exits(int $roomId): array
    {
        $out = [];
        foreach (Db::all(
            'SELECT x.*, r.name AS to_name FROM mud_exits x JOIN mud_rooms r ON r.id = x.to_room WHERE x.from_room = ?',
            [$roomId]
        ) as $x) {
            $out[$x['dir']] = $x;
        }
        return $out;
    }

    public static function itemTemplate(int $id): ?array
    {
        if (!isset(self::$itemTpl[$id])) {
            $r = Db::one('SELECT * FROM mud_item_templates WHERE id = ?', [$id]);
            if (!$r) {
                return null;
            }
            $r['stat_mods'] = $r['stat_mods'] ? json_decode($r['stat_mods'], true) : [];
            $r['effect'] = $r['effect'] ? json_decode($r['effect'], true) : [];
            self::$itemTpl[$id] = $r;
        }
        return self::$itemTpl[$id];
    }

    public static function itemTemplateByVnum(int $vnum): ?array
    {
        $id = (int) Db::val('SELECT id FROM mud_item_templates WHERE vnum = ?', [$vnum]);
        return $id ? self::itemTemplate($id) : null;
    }

    public static function mobTemplate(int $id): ?array
    {
        if (!isset(self::$mobTpl[$id])) {
            $r = Db::one('SELECT * FROM mud_mob_templates WHERE id = ?', [$id]);
            if (!$r) {
                return null;
            }
            foreach (['stats', 'dialogue', 'loot_table'] as $k) {
                $r[$k] = $r[$k] ? json_decode($r[$k], true) : [];
            }
            self::$mobTpl[$id] = $r;
        }
        return self::$mobTpl[$id];
    }

    /** Item instances in a location, with their template merged in. */
    public static function items(string $locType, int $locId, ?int $containerId = null): array
    {
        $sql = 'SELECT * FROM mud_item_instances WHERE loc_type = ? AND loc_id = ? AND '
             . ($containerId === null ? 'container_id IS NULL' : 'container_id = ' . (int) $containerId);
        $rows = Db::all($sql, [$locType, $locId]);
        foreach ($rows as &$r) {
            $r['tpl'] = self::itemTemplate((int) $r['template_id']);
        }
        return array_values(array_filter($rows, static fn ($r) => $r['tpl'] !== null));
    }

    public static function itemInstance(int $id): ?array
    {
        $r = Db::one('SELECT * FROM mud_item_instances WHERE id = ?', [$id]);
        if (!$r) {
            return null;
        }
        $r['tpl'] = self::itemTemplate((int) $r['template_id']);
        return $r['tpl'] ? $r : null;
    }

    public static function moveItem(int $instId, string $locType, int $locId, ?int $containerId = null): void
    {
        Db::q(
            'UPDATE mud_item_instances SET loc_type = ?, loc_id = ?, container_id = ? WHERE id = ?',
            [$locType, $locId, $containerId, $instId]
        );
    }

    public static function spawnItem(int $vnum, string $locType, int $locId): ?int
    {
        $tpl = self::itemTemplateByVnum($vnum);
        if (!$tpl) {
            return null;
        }
        return Db::insert('mud_item_instances', [
            'template_id'  => $tpl['id'],
            'loc_type'     => $locType,
            'loc_id'       => $locId,
            'charges_left' => $tpl['charges'] > 0 ? $tpl['charges'] : -1,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public static function destroyItem(int $instId): void
    {
        Db::q('DELETE FROM mud_item_instances WHERE id = ? OR container_id = ?', [$instId, $instId]);
    }

    /** Live mobs in a room, template merged in. */
    public static function mobs(int $roomId): array
    {
        $rows = Db::all(
            "SELECT * FROM mud_mob_instances WHERE room_id = ? AND state <> 'dead' ORDER BY id",
            [$roomId]
        );
        foreach ($rows as &$r) {
            $r['tpl'] = self::mobTemplate((int) $r['template_id']);
        }
        return array_values(array_filter($rows, static fn ($r) => $r['tpl'] !== null));
    }

    public static function mobInstance(int $id): ?array
    {
        $r = Db::one('SELECT * FROM mud_mob_instances WHERE id = ?', [$id]);
        if (!$r) {
            return null;
        }
        $r['tpl'] = self::mobTemplate((int) $r['template_id']);
        return $r['tpl'] ? $r : null;
    }

    /** Other players present in a room (from BBS-linked player rows). */
    public static function players(int $roomId, int $exceptPlayerId = 0): array
    {
        return Db::all(
            "SELECT * FROM mud_players WHERE room_id = ? AND id <> ? AND last_cmd_at > NOW() - INTERVAL 15 MINUTE",
            [$roomId, $exceptPlayerId]
        );
    }

    public static function shop(int $roomId): ?array
    {
        return Db::one('SELECT * FROM mud_shops WHERE room_id = ?', [$roomId]);
    }

    /** Readable lore in a room. @return list<array{keywords:string,body:string}> */
    public static function roomExtras(int $roomId): array
    {
        return Db::all('SELECT keywords, body FROM mud_room_extras WHERE room_id = ?', [$roomId]);
    }

    /** Match a look/examine keyword against a room's extras; null if none. */
    public static function roomExtra(int $roomId, string $kw): ?string
    {
        $kw = strtolower(trim($kw));
        if ($kw === '') {
            return null;
        }
        foreach (self::roomExtras($roomId) as $e) {
            foreach (explode('|', strtolower($e['keywords'])) as $k) {
                $k = trim($k);
                if ($k !== '' && ($k === $kw || str_contains($k, $kw) || str_contains($kw, $k))) {
                    return $e['body'];
                }
            }
        }
        return null;
    }

    /** @return list<array> stock rows with template merged */
    public static function shopStock(int $shopId): array
    {
        $rows = Db::all('SELECT * FROM mud_shop_stock WHERE shop_id = ? AND qty <> 0 ORDER BY id', [$shopId]);
        foreach ($rows as &$r) {
            $r['tpl'] = self::itemTemplateByVnum((int) $r['template_vnum']);
        }
        return array_values(array_filter($rows, static fn ($r) => $r['tpl'] !== null));
    }

    public static function cfg(string $key, string $default = ''): string
    {
        $v = Db::val('SELECT `value` FROM mud_config WHERE `key` = ?', [$key]);
        return $v === null ? $default : (string) $v;
    }

    public static function setCfg(string $key, string $value): void
    {
        Db::q(
            'INSERT INTO mud_config (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
    }

    public static function event(?int $playerId, string $type, string $detail): void
    {
        Db::insert('mud_events', [
            'player_id'  => $playerId,
            'type'       => $type,
            'detail'     => mb_substr($detail, 0, 250),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Dice roller: "2d6+1", "1d20", "3d4-2". */
    public static function roll(string $dice): int
    {
        if (!preg_match('/^(\d+)d(\d+)([+-]\d+)?$/i', trim($dice), $m)) {
            return (int) $dice;
        }
        $n = (int) $m[1];
        $s = (int) $m[2];
        $t = 0;
        for ($i = 0; $i < $n; $i++) {
            $t += random_int(1, max(1, $s));
        }
        return $t + (int) ($m[3] ?? 0);
    }
}
