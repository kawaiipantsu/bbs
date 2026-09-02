<?php
/**
 * THUGS(red) BBS - configuration sample.
 *
 * Copy this file to app/config.php and edit, OR just set the BBS_* environment
 * variables (the Docker image does the latter). app/config.php is gitignored and
 * MUST NOT be committed. Recommended permissions: chmod 640.
 *
 * Generate the crypto keys with:
 *   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
 */

$env = static fn (string $k, $default = null) => (($v = getenv($k)) !== false && $v !== '') ? $v : $default;

return [
    // ---------------------------------------------------------------------
    // Environment
    // ---------------------------------------------------------------------
    'env'          => $env('BBS_ENV', 'production'),
    'debug'        => filter_var($env('BBS_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'canonical'    => $env('BBS_CANONICAL', 'https://bbs.thugs.red'),
    'telnet_host'  => $env('BBS_TELNET_HOST', 'bbs.thugs.red'),
    'timezone'     => $env('BBS_TZ', 'UTC'),

    // ---------------------------------------------------------------------
    // Database (MariaDB / MySQL)
    // ---------------------------------------------------------------------
    'db' => [
        'host'    => $env('BBS_DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('BBS_DB_PORT', '3306'),
        'name'    => $env('BBS_DB_NAME', 'projects_bbs'),
        'user'    => $env('BBS_DB_USER', 'bbs'),
        'pass'    => $env('BBS_DB_PASS', 'CHANGE-ME'),
        'charset' => 'utf8mb4',
    ],

    // ---------------------------------------------------------------------
    // Redis (optional). enabled=false runs purely on the database.
    // ---------------------------------------------------------------------
    'redis' => [
        'enabled' => filter_var($env('BBS_REDIS_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
        'host'    => $env('BBS_REDIS_HOST', '127.0.0.1'),
        'port'    => (int) $env('BBS_REDIS_PORT', '6379'),
        'prefix'  => $env('BBS_REDIS_PREFIX', 'bbs:'),
        'db'      => (int) $env('BBS_REDIS_DB', '0'),
    ],

    // ---------------------------------------------------------------------
    // Beanstalkd (optional). Tubes are namespaced with tube_prefix.
    // ---------------------------------------------------------------------
    'beanstalk' => [
        'enabled'     => filter_var($env('BBS_BEANSTALK_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
        'host'        => $env('BBS_BEANSTALK_HOST', '127.0.0.1'),
        'port'        => (int) $env('BBS_BEANSTALK_PORT', '11300'),
        'tube_prefix' => $env('BBS_BEANSTALK_PREFIX', 'bbs/'),
    ],

    // ---------------------------------------------------------------------
    // Crypto - base64 of 32 random bytes each. NEVER share or commit.
    // If left as defaults, insecure dev keys are generated per-boot.
    // ---------------------------------------------------------------------
    'crypto' => [
        'sodium_key' => $env('BBS_SODIUM_KEY', base64_encode(hash('sha256', 'insecure-dev-sodium', true))),
        'app_key'    => $env('BBS_APP_KEY', base64_encode(hash('sha256', 'insecure-dev-app', true))),
        'csrf_salt'  => $env('BBS_CSRF_SALT', 'insecure-dev-csrf-salt'),
    ],

    'session' => [
        'cookie'       => 'bbs_node',
        'idle_ttl'     => (int) $env('BBS_SESSION_IDLE', '3600'),
        'absolute_ttl' => (int) $env('BBS_SESSION_MAX', (string) (86400 * 7)),
        'bind_network' => filter_var($env('BBS_SESSION_BIND_NET', 'true'), FILTER_VALIDATE_BOOL),
    ],

    // First SysOp account - created/updated by `php mysql/migrate.php --seed`.
    'sysop' => [
        'handle'   => $env('BBS_SYSOP_HANDLE', 'sysop'),
        'password' => $env('BBS_SYSOP_PASSWORD', 'letmein'),
    ],

    'terminal' => [
        'cols'  => (int) $env('BBS_TERM_COLS', '132'),
        'rows'  => (int) $env('BBS_TERM_ROWS', '50'),
        'baud'  => (int) $env('BBS_BAUD', '57600'),
        'nodes' => (int) $env('BBS_NODES', '8'),
    ],

    'discord' => [
        'default_webhook' => $env('BBS_DISCORD_WEBHOOK', ''),
    ],

    'proxy' => [
        'trust_forwarded_proto' => filter_var($env('BBS_TRUST_XFP', 'true'), FILTER_VALIDATE_BOOL),
    ],

    // File / media storage. Empty = <repo>/storage.
    'storage' => [
        'path' => $env('BBS_STORAGE_PATH', ''),
    ],
];
