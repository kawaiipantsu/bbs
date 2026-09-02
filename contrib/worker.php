<?php
/**
 * THUGS(red) BBS - background worker.
 *
 * Drains the namespaced beanstalkd tubes (bbs/discord, bbs/mail, bbs/news,
 * bbs/system). If beanstalkd is unavailable it falls back to polling the
 * jobs_log table, so the same script works under systemd (daemon) or cron
 * (one-shot with --once).
 *
 *   php contrib/worker.php            run forever, reserving jobs
 *   php contrib/worker.php --once     drain what's ready, then exit (cron mode)
 *   php contrib/worker.php --news     just refresh the news wire and exit
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Admin\AuditLog;
use Bbs\Admin\DiscordHook;
use Bbs\Bbs\NewsFetcher;
use Bbs\Core\Beanstalk;
use Bbs\Core\Config;
use Bbs\Core\Db;

Config::loadSettings();

$once = in_array('--once', $argv, true);
$log  = fn (string $m) => fwrite(STDOUT, '[' . date('H:i:s') . "] $m\n");

if (in_array('--news', $argv, true)) {
    $n = NewsFetcher::run();
    $log("news: fetched $n new items");
    exit(0);
}

$TUBES = ['discord', 'mail', 'news', 'system'];

/* -------- job handlers ------------------------------------------------ */
function handle(array $job, callable $log): bool
{
    $tube  = $job['tube'] ?? 'system';
    $event = $job['event'] ?? '';

    try {
        if ($tube === 'discord' || str_starts_with($event, 'ticket.') || str_starts_with($event, 'message.') || str_starts_with($event, 'user.') || $event === 'sysop.page') {
            $res = DiscordHook::dispatch($event, $job);
            $log("discord[$event] " . json_encode($res, JSON_UNESCAPED_SLASHES));
            return true;
        }
        if ($tube === 'news' || $event === 'news.refresh') {
            $n = isset($job['category']) ? NewsFetcher::runCategory($job['category']) : NewsFetcher::run();
            $log("news.refresh -> $n new");
            return true;
        }
        if ($tube === 'mail') {
            // no MTA wired yet; log and drop so the tube stays clean
            $log('mail: ' . json_encode($job, JSON_UNESCAPED_SLASHES));
            return true;
        }
        $log("system: " . json_encode($job, JSON_UNESCAPED_SLASHES));
        return true;
    } catch (\Throwable $e) {
        $log("ERROR handling job: " . $e->getMessage());
        return false;
    }
}

/* -------- beanstalkd path ------------------------------------------- */
$useBeanstalk = Beanstalk::enabled();
if ($useBeanstalk) {
    try {
        $bs = Beanstalk::fromConfig();
        foreach ($TUBES as $t) {
            $bs->watch($t);
        }
        $bs->ignore('default');
        $log('worker up on beanstalkd; tubes: ' . implode(', ', $TUBES));
    } catch (\Throwable $e) {
        $log('beanstalkd unavailable (' . $e->getMessage() . '); using DB fallback');
        $useBeanstalk = false;
    }
}

/* periodic news refresh bookkeeping */
$lastNews = 0;

do {
    $did = 0;

    if ($useBeanstalk) {
        $j = $bs->reserve(5);
        if ($j) {
            $body = $j['body'];
            $ok = handle($body, $log);
            $ok ? $bs->delete($j['id']) : $bs->bury($j['id']);
            $did++;
        }
    } else {
        $rows = Db::all(
            "SELECT * FROM jobs_log WHERE status = 'pending' AND (run_after IS NULL OR run_after <= NOW())
             ORDER BY id LIMIT 20"
        );
        foreach ($rows as $row) {
            Db::update('jobs_log', ['status' => 'running', 'attempts' => (int) $row['attempts'] + 1], ['id' => $row['id']]);
            $body = json_decode($row['payload'], true) ?: [];
            $body['tube'] = $row['tube'];
            $ok = handle($body, $log);
            Db::update('jobs_log', [
                'status' => $ok ? 'done' : 'failed',
                'result' => $ok ? 'ok' : 'handler returned false',
            ], ['id' => $row['id']]);
            $did++;
        }
        if (!$did && !$once) {
            sleep(5);
        }
    }

    // hourly news pull even with no explicit job
    if (time() - $lastNews > 3600) {
        $lastNews = time();
        try {
            $n = NewsFetcher::run();
            if ($n) {
                $log("scheduled news pull: +$n");
            }
        } catch (\Throwable $e) {
            $log('news pull failed: ' . $e->getMessage());
        }
    }
} while (!$once || $did > 0);

$log('worker done');
