<?php
/**
 * THUGS(red) BBS - application bootstrap.
 *
 * Loaded by html/index.php (web), mysql/migrate.php and contrib/worker.php (cli).
 * Sets up autoloading, configuration, error handling and the service container.
 */

declare(strict_types=1);

define('BBS_ROOT', dirname(__DIR__));
define('BBS_APP', __DIR__);
define('BBS_START', microtime(true));

/*
 * All user file / media storage lives OUTSIDE the web root in ./storage so a
 * dedicated disk can be mounted there (noexec, nodev, quota, ...). Override the
 * location with the 'storage.path' config key if the mount is elsewhere.
 */
define('BBS_STORAGE', getenv('BBS_STORAGE_PATH') ?: BBS_ROOT . '/storage');

// ---------------------------------------------------------------------------
// PSR-4 autoloader for Bbs\  ->  app/src/  (no composer install required)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'Bbs\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BBS_APP . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Optional composer autoloader (only if someone ran `composer install`).
if (is_file(BBS_ROOT . '/vendor/autoload.php')) {
    require BBS_ROOT . '/vendor/autoload.php';
}

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
$configFile = BBS_APP . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("BBS is not configured. Copy app/config.sample.php to app/config.php.\n");
}

\Bbs\Core\Config::load(require $configFile);

// storage.path config override wins over the ./storage default
$storageOverride = \Bbs\Core\Config::get('storage.path');
if (is_string($storageOverride) && $storageOverride !== '' && !defined('BBS_STORAGE_RESOLVED')) {
    // BBS_STORAGE is already defined; expose the effective path for Storage::
    define('BBS_STORAGE_RESOLVED', rtrim($storageOverride, '/'));
} else {
    define('BBS_STORAGE_RESOLVED', BBS_STORAGE);
}

date_default_timezone_set(\Bbs\Core\Config::get('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
$debug = (bool) \Bbs\Core\Config::get('debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    error_log('[BBS] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'error'   => 'CARRIER LOST - internal error',
        'detail'  => $debug ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : null,
    ], JSON_UNESCAPED_SLASHES);
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
