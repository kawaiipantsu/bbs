<?php
/**
 * THUGS(red) BBS - Hackers-MUD world tick.
 *
 * Drives the MUD's living world: mob respawns, wandering, HP/energy regen,
 * hunger & thirst decay, expiring buffs, and aggressive mobs jumping idle
 * players. The MUD also ticks itself lazily at the top of every player command
 * (Bbs\Mud\Tick::maybeRun), so this cron job is OPTIONAL - it just keeps the
 * world breathing when nobody is connected (respawns, patrols returning home).
 *
 *   php contrib/mud-tick.php           run one tick and exit  (cron mode)
 *   php contrib/mud-tick.php --loop    run forever, ticking every INTERVAL secs
 *
 * Cron: every minute is fine (the tick self-throttles to its own interval).
 *   * * * * *  /usr/bin/php /var/www/.../contrib/mud-tick.php >> .../storage/logs/mud.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Core\Config;
use Bbs\Core\Db;
use Bbs\Mud\Tick;
use Bbs\Mud\World;

Config::loadSettings();

$loop = in_array('--loop', $argv, true);
$stamp = static fn (): string => '[' . date('Y-m-d H:i:s') . '] ';

// bail cleanly if the MUD world has never been built
if (!Db::tableExists('mud_config') || (int) Db::val('SELECT COUNT(*) FROM mud_rooms') === 0) {
    fwrite(STDERR, $stamp() . "MUD world not built yet - run: php mysql/mud_world.php\n");
    exit(0);
}

$runOnce = static function () use ($stamp): void {
    $t0 = microtime(true);
    try {
        // force a run regardless of the lazy-throttle window
        World::setCfg('last_tick', '0');
        Tick::run();
        World::setCfg('last_tick', (string) time());
        World::setCfg('tick_lock', '0');
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $online = (int) Db::val("SELECT COUNT(*) FROM mud_players WHERE last_cmd_at > NOW() - INTERVAL 15 MINUTE");
        echo $stamp() . "tick ok  {$ms}ms  online={$online}\n";
    } catch (\Throwable $e) {
        World::setCfg('tick_lock', '0');
        fwrite(STDERR, $stamp() . 'tick FAILED: ' . $e->getMessage() . "\n");
    }
};

if (!$loop) {
    $runOnce();
    exit(0);
}

$interval = max(5, (int) World::cfg('tick_interval', '18'));
echo $stamp() . "mud-tick loop starting (every {$interval}s). Ctrl-C to stop.\n";
$stop = false;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$stop) { $stop = true; });
    pcntl_signal(SIGINT, function () use (&$stop) { $stop = true; });
}
while (!$stop) {
    $runOnce();
    for ($i = 0; $i < $interval && !$stop; $i++) {
        sleep(1);
    }
}
echo $stamp() . "mud-tick loop stopped.\n";
