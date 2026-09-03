<?php

declare(strict_types=1);

namespace Bbs\Mud;

use Bbs\Core\Db;

/**
 * Turn-based combat. A single `engage()` call resolves several rounds and
 * returns the fight log; it stops on a kill, on death, on flee, or when the
 * player drops low so they can decide (attack again / flee / heal).
 */
final class Combat
{
    private const MAX_ROUNDS = 12;

    /** @return list<string> */
    public static function engage(int $playerId, int $mobInstId, bool $firstHit = false): array
    {
        $log = [];
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $p = Player::byId($playerId);
            $mi = World::mobInstance($mobInstId);
            if (!$p) {
                return $log;
            }
            if (!$mi || $mi['state'] === 'dead' || (int) $mi['room_id'] !== (int) $p['room_id']) {
                Db::q("UPDATE mud_players SET state='idle', target_mob=NULL WHERE id=?", [$playerId]);
                $log[] = '|08Your target is gone.';
                return $log;
            }

            // --- player attacks ---
            $mob = $mi['tpl'];
            [$dice, $bonus, $verb] = Player::weapon($p);
            $atk = random_int(1, 20) + Player::attackRating($p);
            $def = 10 + (int) $mob['ac'];
            $swing = $verb === 'shoot' ? 'gun' : ($verb === 'strike' ? 'blade' : 'swing');
            if ($atk >= $def || random_int(1, 20) === 20) {
                $crit = $atk - Player::attackRating($p) >= 19;
                $dmg = max(1, World::roll($dice) + $bonus - intdiv((int) $mob['ac'], 4));
                if ($crit) {
                    $dmg = (int) round($dmg * 1.8);
                }
                $mhp = (int) $mi['hp'] - $dmg;
                Db::q('UPDATE mud_mob_instances SET hp = ?, state = "fighting", target_player = ?, last_act_at = NOW() WHERE id = ?',
                    [$mhp, $playerId, $mobInstId]);
                Mud::sfx($swing, $crit ? 'crit' : 'hit');
                $log[] = sprintf('|10You %s %s for |15%d|10 damage%s.', $verb, $mob['name'], $dmg, $crit ? ' |11(CRIT!)' : '');
                if ($mhp <= 0) {
                    return array_merge($log, self::kill($playerId, $mobInstId));
                }
            } else {
                Mud::sfx($swing);
                $log[] = sprintf('|08You %s at %s and miss.', $verb, $mob['name']);
            }

            // player might flee via low-HP auto-stop below; mob acts:
            $st = Player::effectiveStats($p);
            $matk = random_int(1, 20) + (int) $mob['level'] + intdiv((int) ($mob['stats']['body'] ?? 5), 2);
            $pdef = 10 + Player::armorClass($p);
            if ($matk >= $pdef) {
                $mdmg = max(1, World::roll($mob['damage_dice'] ?: '1d4') - intdiv(Player::armorClass($p), 3));
                $php = (int) $p['hp'] - $mdmg;
                Db::q('UPDATE mud_players SET hp = ?, state = "fighting", target_mob = ? WHERE id = ?', [$php, $mobInstId, $playerId]);
                Mud::sfx($php > 0 && $php <= (int) $p['max_hp'] * 0.3 ? 'hurt' : 'enemyhit');
                $log[] = sprintf('|09%s hits you for |15%d|09 damage.', ucfirst($mob['name']), $mdmg);
                if ($php <= 0) {
                    Mud::sfx('death');
                    return array_merge($log, Player::die($playerId, 'killed by ' . $mob['name']));
                }
            } else {
                $log[] = sprintf('|08%s lunges and misses.', ucfirst($mob['name']));
            }

            // coward mobs bolt when hurt
            if (str_contains((string) $mob['behavior'], 'coward') && (int) $mi['hp'] > 0
                && (int) $mi['hp'] < (int) $mob['max_hp'] * 0.35 && random_int(1, 3) === 1) {
                Db::q("UPDATE mud_mob_instances SET state='fleeing' WHERE id=?", [$mobInstId]);
                $log[] = "|08{$mob['name']} breaks and runs!";
                self::mobFlee($mobInstId);
                Db::q("UPDATE mud_players SET state='idle', target_mob=NULL WHERE id=?", [$playerId]);
                return $log;
            }

            $p = Player::byId($playerId);
            if ((int) $p['hp'] <= (int) $p['max_hp'] * 0.30) {
                $log[] = '|12You are badly hurt. (attack again · flee · use a stim)';
                return $log;
            }
        }
        $log[] = '|08You break contact to catch your breath. (attack to continue)';
        Db::q("UPDATE mud_players SET state='idle' WHERE id=?", [$playerId]);
        return $log;
    }

    /** Aggressive mob starts a fight from the tick. @return list<string> */
    public static function mobStrike(int $mobInstId, int $playerId): array
    {
        $mi = World::mobInstance($mobInstId);
        $p = Player::byId($playerId);
        if (!$mi || !$p || (int) $mi['room_id'] !== (int) $p['room_id']) {
            return [];
        }
        $mob = $mi['tpl'];
        Db::q('UPDATE mud_mob_instances SET state="fighting", target_player=? WHERE id=?', [$playerId, $mobInstId]);
        Db::q('UPDATE mud_players SET state="fighting", target_mob=? WHERE id=? AND target_mob IS NULL', [$mobInstId, $playerId]);
        Mud::sfx('aggro');
        $matk = random_int(1, 20) + (int) $mob['level'];
        $pdef = 10 + Player::armorClass($p);
        if ($matk >= $pdef) {
            $dmg = max(1, World::roll($mob['damage_dice'] ?: '1d4') - intdiv(Player::armorClass($p), 3));
            $php = (int) $p['hp'] - $dmg;
            Db::q('UPDATE mud_players SET hp = ? WHERE id = ?', [$php, $playerId]);
            if ($php <= 0) {
                return array_merge(["|09{$mob['name']} ambushes you for $dmg!"], Player::die($playerId, 'ambushed by ' . $mob['name']));
            }
            $kw = explode(' ', $mob['keywords'])[0];
            return ["|09{$mob['name']} attacks you for |15$dmg|09!  |12(kill $kw  ·  flee)"];
        }
        return ["|08{$mob['name']} takes a swing at you and misses."];
    }

    /** @return list<string> loot + xp on a kill */
    private static function kill(int $playerId, int $mobInstId): array
    {
        $mi = World::mobInstance($mobInstId);
        $p = Player::byId($playerId);
        $mob = $mi['tpl'];
        $out = ["|11{$mob['name']} is dropped. It stops twitching."];
        Mud::sfx('kill');

        Db::q("UPDATE mud_mob_instances SET state='dead', hp=0, died_at=NOW(), target_player=NULL WHERE id=?", [$mobInstId]);
        Db::q("UPDATE mud_players SET state='idle', target_mob=NULL, kills=kills+1 WHERE id=?", [$playerId]);

        // killing NCPD or a corp responder raises your NCPD heat
        if (in_array($mob['faction'], ['police'], true)) {
            $bump = str_contains((string) $mob['flags'], 'hunter') ? 25 : 12;
            $w = Player::addWanted($playerId, $bump);
            $out[] = $w >= 60
                ? '|12Every scanner in the district just lit up with your face. MaxTac inbound.'
                : '|09You just killed a cop. NCPD does not forget that.';
            World::event($playerId, 'wanted', "{$p['name']} killed {$mob['name']}.");
        } elseif (in_array($mob['faction'], ['corpo', 'arasaka'], true) && str_contains((string) $mob['flags'], 'boss')) {
            Player::addWanted($playerId, 15);
            $out[] = '|09A corp exec flatlining on their own floor gets a response. Watch your back.';
        }

        // money
        $money = random_int((int) $mob['money_min'], max((int) $mob['money_min'], (int) $mob['money_max']));
        if ($money > 0) {
            Db::q('UPDATE mud_players SET money = money + ? WHERE id = ?', [$money, $playerId]);
            $out[] = "|14You scavenge ¥$money.";
        }

        // loot
        foreach (($mob['loot_table'] ?? []) as $drop) {
            if (random_int(1, 100) <= (int) ($drop['chance'] ?? 0)) {
                $iid = World::spawnItem((int) $drop['vnum'], 'room', (int) $p['room_id']);
                if ($iid) {
                    $t = World::itemInstance($iid)['tpl'];
                    $out[] = "|08{$mob['name']} drops {$t['name']}.";
                }
            }
        }

        // xp + skill xp
        $xp = (int) $mob['xp_reward'] + random_int(0, 2);
        $lvlMsgs = Player::grantXp($playerId, $xp);
        if ($lvlMsgs) {
            Mud::sfx('levelup');
        }
        $out = array_merge($out, ["|10You gain |15$xp|10 XP."], $lvlMsgs);
        $ranged = ($eq = Player::equipmentSlot($playerId, 'wield')) && str_contains($eq['tpl']['flags'], 'ranged');
        $sk = Player::trainSkill($playerId, $ranged ? 'firearms' : 'melee', random_int(3, 7));
        if ($sk) {
            $out[] = "|11Your $sk improves.";
        }
        if ((int) $mob['level'] >= (int) $p['level'] + 2 || str_contains((string) $mob['flags'], 'boss')) {
            Db::q('UPDATE mud_players SET street_cred = street_cred + ? WHERE id = ?', [max(1, (int) $mob['level']), $playerId]);
            $out[] = '|13Your street cred rises.';
            World::event($playerId, 'kill', "{$p['name']} took down {$mob['name']}.");
        }

        // quest + bounty progress
        Quests::progress($playerId, 'kill', explode(' ', $mob['keywords'])[0]);
        Quests::progress($playerId, 'kill', 'vnum:' . $mob['vnum']);
        Mud::bountyKill($playerId, $mob['keywords'] . ' ' . $mob['faction']);
        return $out;
    }

    private static function mobFlee(int $mobInstId): void
    {
        $mi = World::mobInstance($mobInstId);
        if (!$mi) {
            return;
        }
        $exits = array_values(World::exits((int) $mi['room_id']));
        if ($exits) {
            $x = $exits[array_rand($exits)];
            Db::q("UPDATE mud_mob_instances SET room_id=?, state='idle' WHERE id=?", [$x['to_room'], $mobInstId]);
        } else {
            Db::q("UPDATE mud_mob_instances SET state='idle' WHERE id=?", [$mobInstId]);
        }
    }
}
