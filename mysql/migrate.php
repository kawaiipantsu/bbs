<?php
/**
 * THUGS(red) BBS - schema installer / migration runner.
 *
 *   php mysql/migrate.php            Apply schema.sql + any pending migrations
 *   php mysql/migrate.php --seed     ...then load seed.sql and create the SysOp
 *   php mysql/migrate.php --fresh    DROP every BBS table first (destructive!)
 *
 * Safe to run repeatedly; schema.sql uses CREATE TABLE IF NOT EXISTS and
 * migrations are tracked in schema_migrations.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Core\Db;
use Bbs\Core\Config;
use Bbs\Auth\Password;

$args  = array_slice($argv, 1);
$seed  = in_array('--seed', $args, true);
$fresh = in_array('--fresh', $args, true);

function run_sql_file(string $path): int
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("cannot read $path");
    }
    // Strip line comments, then split on ";" at end of line.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*[\r\n]/', $sql) ?: []),
        static fn ($s) => $s !== '' && $s !== ';'
    );
    $n = 0;
    foreach ($statements as $stmt) {
        Db::pdo()->exec($stmt);
        $n++;
    }
    return $n;
}

echo "THUGS(red) BBS :: migrate\n";
echo "DB: " . Config::get('db.name') . " @ " . Config::get('db.host') . "\n\n";

Db::pdo(); // connect (throws on failure)

if ($fresh) {
    echo "!! --fresh: dropping all BBS tables\n";
    $tables = Db::all("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()");
    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $row) {
        Db::pdo()->exec('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        echo "   drop {$row['t']}\n";
    }
    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\n";
}

echo "-> schema.sql\n";
$count = run_sql_file(__DIR__ . '/schema.sql');
echo "   $count statements OK\n";

// Incremental migrations
$dir = __DIR__ . '/migrations';
$applied = array_column(Db::all('SELECT version FROM schema_migrations'), 'version');
$files = glob($dir . '/*.sql') ?: [];
sort($files);
$pending = 0;
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) {
        continue;
    }
    echo "-> migration $version\n";
    // NB: no transaction - DDL (CREATE/ALTER TABLE) implicitly commits in MySQL,
    // so a wrapping tx would have nothing left to commit.
    run_sql_file($file);
    Db::q(
        'INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at)',
        [$version, date('Y-m-d H:i:s')]
    );
    $pending++;
}
echo $pending === 0 ? "   no pending migrations\n" : "   $pending migration(s) applied\n";

$tableCount = (int) Db::val(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
);
echo "\nTables now: $tableCount\n";

if ($seed) {
    echo "\n-> seed.sql\n";
    $count = run_sql_file(__DIR__ . '/seed.sql');
    echo "   $count statements OK\n";

    // SysOp account (password only ever from config, never from git)
    $sysHandle = (string) Config::get('sysop.handle', 'sysop');
    $sysPass   = (string) Config::get('sysop.password', 'letmein');
    $hash      = Password::hash($sysPass);

    $uid = Db::val('SELECT id FROM users WHERE handle = ?', [$sysHandle]);
    if ($uid) {
        Db::update('users', [
            'password_hash'        => $hash,
            'must_change_password' => 1,
            'status'               => 'active',
        ], ['id' => $uid]);
        echo "   sysop '$sysHandle' updated (id $uid)\n";
    } else {
        $uid = Db::insert('users', [
            'handle'               => $sysHandle,
            'password_hash'        => $hash,
            'must_change_password' => 1,
            'status'               => 'active',
            'tagline'              => 'The one who answers the phone.',
            'location'             => 'The Machine Room',
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        echo "   sysop '$sysHandle' created (id $uid)\n";
    }
    // grant sysop role
    $rid = Db::val("SELECT id FROM roles WHERE slug = 'sysop'");
    if ($rid) {
        Db::q('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$uid, $rid]);
    }
    echo "   NOTE: sysop must change password on first login.\n";
}

echo "\nDone.\n";
