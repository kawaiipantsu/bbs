<?php
/**
 * THUGS(red) BBS - nightly maintenance. Run from cron (see contrib/crontab).
 *   - expire dead sessions and stale chat presence
 *   - close call_log rows for sessions that vanished
 *   - trim login_attempts / audit noise
 *   - ping search engines with the sitemap
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Auth\Session;
use Bbs\Core\Config;
use Bbs\Core\Db;

Config::loadSettings();
$log = fn (string $m) => fwrite(STDOUT, '[' . date('c') . "] $m\n");

$gc = Session::gc();
$log("sessions purged: $gc");

$chat = Db::q('DELETE FROM chat_presence WHERE last_seen_at < NOW() - INTERVAL 1 HOUR')->rowCount();
$log("chat presence purged: $chat");

$calls = Db::q(
    'UPDATE call_log c
     LEFT JOIN sessions s ON s.id = c.session_id
     SET c.disconnected_at = COALESCE(c.disconnected_at, NOW()),
         c.seconds = COALESCE(c.seconds, TIMESTAMPDIFF(SECOND, c.connected_at, NOW()))
     WHERE c.disconnected_at IS NULL AND s.id IS NULL'
)->rowCount();
$log("call_log rows closed: $calls");

$att = Db::q('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY')->rowCount();
$log("login_attempts pruned: $att");

$jobs = Db::q("DELETE FROM jobs_log WHERE status IN ('done','failed') AND updated_at < NOW() - INTERVAL 7 DAY")->rowCount();
$log("jobs_log pruned: $jobs");

// ping search engines
$sitemap = rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/') . '/sitemap.xml';
foreach ([
    'https://www.google.com/ping?sitemap=' . urlencode($sitemap),
    'https://www.bing.com/ping?sitemap=' . urlencode($sitemap),
] as $u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    $log('pinged ' . parse_url($u, PHP_URL_HOST) . ' -> ' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);
}

$log('maintenance complete');
