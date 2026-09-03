<?php
/**
 * THUGS(red) BBS - front controller.
 *
 * Apache rewrites every non-file request here (see html/.htaccess).
 * Nothing else in html/ is executable PHP.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Core\Config;
use Bbs\Core\Request;
use Bbs\Core\Response;
use Bbs\Core\Router;
use Bbs\Http\Controllers\ApiController;
use Bbs\Http\Controllers\ChatController;
use Bbs\Http\Controllers\HackersMudController;
use Bbs\Http\Controllers\MudApiController;
use Bbs\Http\Controllers\PageController;
use Bbs\Http\Controllers\SeoController;

$request = Request::capture();

// Load DB-backed settings on top of file config (safe no-op if not migrated).
Config::loadSettings();

// ---------------------------------------------------------------------
//  Security headers for every dynamic response
// ---------------------------------------------------------------------
// per-response nonce so the shell's tiny bootstrap <script> can run under a
// strict script-src (no 'unsafe-inline'); exposed to views via BBS_CSP_NONCE.
$cspNonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
define('BBS_CSP_NONCE', $cspNonce);

if (!headers_sent()) {
    header_remove('X-Powered-By');
    $csp = implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "img-src 'self' data: blob:",
        "media-src 'self' blob: data:",
        "font-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' 'nonce-{$cspNonce}'",
        "connect-src 'self'",
        "worker-src 'self' blob:",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Vary: Cookie, Accept');
}

// Reject cross-origin state-changing requests (defence in depth; SameSite=Lax also helps).
if (in_array($request->method, ['POST', 'PUT', 'DELETE'], true)) {
    $origin = $request->header('origin');
    $canonHost = parse_url((string) Config::get('canonical', 'https://bbs.thugs.red'), PHP_URL_HOST);
    if ($origin !== null && $origin !== '') {
        $oHost = parse_url($origin, PHP_URL_HOST);
        if ($oHost !== $canonHost && $oHost !== ($_SERVER['HTTP_HOST'] ?? '')) {
            Response::error('Cross-origin request refused.', 403)->send();
            return;
        }
    }
}

// ---------------------------------------------------------------------
//  Routes
// ---------------------------------------------------------------------
$router = new Router();

// --- terminal shell + deep links ---
$router->get('/',                 [PageController::class, 'shell']);
$router->get('/bbs',              [PageController::class, 'shell']);
$router->get('/b/{slug}',         [PageController::class, 'board']);
$router->get('/m/{id}',           [PageController::class, 'message']);
$router->get('/u/{handle}',       [PageController::class, 'profile']);
$router->get('/news/{cat}',       [PageController::class, 'news']);
$router->get('/g/{slug}',         [PageController::class, 'game']);

// --- API: the terminal talks here ---
$router->post('/api/session',        [ApiController::class, 'session']);
$router->post('/api/action',         [ApiController::class, 'action']);
$router->get('/api/whoami',          [ApiController::class, 'whoami']);
$router->post('/api/auth/logout',    [ApiController::class, 'logout']);
$router->get('/api/ticker',          [ApiController::class, 'ticker']);

// --- Hackers-MUD standalone graphical client ---
// (the shell itself is html/hackers-mud/index.php, served by Apache's
//  DirectoryIndex; only the virtual paths below reach the front controller)
$router->get('/hackers-mud/og.png',    [HackersMudController::class, 'og']);
$router->get('/hackers-mud/banner.png', [HackersMudController::class, 'banner']);
$router->post('/api/mud/login',      [MudApiController::class, 'login']);
$router->post('/api/mud/logout',     [MudApiController::class, 'logout']);
$router->post('/api/mud/archetype',  [MudApiController::class, 'archetype']);
$router->get('/api/mud/whoami',      [MudApiController::class, 'whoami']);
$router->get('/api/mud/state',       [MudApiController::class, 'state']);
$router->post('/api/mud/cmd',        [MudApiController::class, 'cmd']);
$router->get('/api/mud/players',     [MudApiController::class, 'players']);
$router->get('/api/mud/inbox',       [MudApiController::class, 'inbox']);
$router->post('/api/mud/sms',        [MudApiController::class, 'sms']);
$router->get('/api/mud/itemdex',     [MudApiController::class, 'itemdex']);
$router->get('/hackers-mud/items',   [HackersMudController::class, 'items']);

// --- chat (SSE + post) ---
$router->get('/api/chat/stream',  [ChatController::class, 'stream']);
$router->post('/api/chat/say',    [ChatController::class, 'say']);
$router->get('/api/chat/poll',    [ChatController::class, 'poll']);

// --- file download (auth + audit inside) ---
$router->get('/api/file/{id}',    [ApiController::class, 'download']);

// --- SEO / social ---
$router->get('/robots.txt',           [SeoController::class, 'robots']);
$router->get('/sitemap.xml',          [SeoController::class, 'sitemap']);
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest']);
$router->get('/og/{slug}.png',        [SeoController::class, 'og']);
$router->get('/.well-known/security.txt', [SeoController::class, 'securityTxt']);

try {
    $router->dispatch($request)->send();
} catch (Throwable $e) {
    error_log('[BBS] route error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = Config::bool('debug', false);
    Response::json([
        'error'  => 'CARRIER LOST',
        'detail' => $debug ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : null,
    ], 500)->send();
}
