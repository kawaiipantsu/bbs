<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Session;
use Bbs\Bbs\Context;
use Bbs\Bbs\Engine;
use Bbs\Bbs\PhoneNumber;
use Bbs\Core\Config;
use Bbs\Core\Crypto;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Core\Response;

/**
 * The JSON protocol the browser terminal speaks. Every screen the user sees is
 * a "frame" returned from here.
 */
final class ApiController
{
    /** POST /api/session - the "call connects". */
    public function session(Request $req): Response
    {
        $session = Session::start($req);

        // one call_log row per session
        if (!$session->get('call_id')) {
            $callId = Db::insert('call_log', [
                'session_id'   => $session->id,
                'user_id'      => $session->userId,
                'handle'       => $session->handle(),
                'ip'           => $session->ip,
                'ip_phone'     => $session->ipPhone,
                'node'         => $session->node,
                'baud'         => Config::int('baud', Config::int('terminal.baud', 57600)),
                'user_agent'   => $req->userAgent(),
                'connected_at' => date('Y-m-d H:i:s'),
            ]);
            $session->put('call_id', $callId);
            AuditLog::bind($session);
            AuditLog::record('call.connect', 'session', $session->id, $session->ipPhone . ' connected on node ' . $session->node);
        }

        $engine = new Engine($session, $req);

        // Maintenance mode: non-staff get a busy signal, but still reach the
        // login screen so a SysOp can authenticate their way through.
        $maintenance = Config::bool('maintenance', false) && $engine->rank() < 80;

        $frame = $engine->boot();
        if ($maintenance) {
            $frame = $this->busyFrame($session, $engine);
        }
        $session->save();

        $baud  = Config::int('baud', Config::int('terminal.baud', 57600));
        $phone = $session->ipPhone;

        return Response::json([
            'connection' => [
                'phone'        => $phone,
                'dial_digits'  => PhoneNumber::digits($phone),
                'ip'           => $session->ip,
                'node'         => $session->node,
                'nodes_total'  => Config::int('nodes', Config::int('terminal.nodes', 8)),
                'nodes_free'   => Session::nodesFree(),
                'baud'         => $baud,
                'maintenance'  => $maintenance,
                'maintenance_msg' => $maintenance ? Config::setting('maintenance_msg', 'The board is down for maintenance.') : '',
                'telnet_host'  => Config::setting('telnet_host', (string) Config::get('telnet_host', 'bbs.thugs.red')),
                'site_name'    => Config::setting('site_name', 'THUGS(red) BBS'),
                'sound_default' => Config::bool('sound_default', false),
                'cols'         => Config::termCols(),
                'rows'         => Config::termRows(),
                'font_scale'   => Config::fontScale(),
                'crt'          => [
                    'intensity'  => (float) Config::setting('crt_intensity', '0.85'),
                    'scanlines'  => Config::bool('crt_scanlines', true),
                    'flicker'    => Config::bool('crt_flicker', true),
                    'curvature'  => Config::bool('crt_curvature', true),
                ],
                'csrf'         => $session->csrf(),
                'logged_in'    => !$session->isGuest(),
                'handle'       => $session->handle(),
            ],
            'frame' => $frame,
        ]);
    }

    private function busyFrame(Session $session, Engine $engine): array
    {
        $msg = Config::setting('maintenance_msg', 'The board is down for maintenance.');
        $f = \Bbs\Bbs\Frame::make('screen')->title('Busy')->mode('menu')
            ->meta(['busy' => true])->blank()->blank()
            ->pipe('|12   ░▒▓  BUSY  ▓▒░')->blank()
            ->pipe('|11   NO CARRIER - the line is engaged.')->blank()
            ->block('|07   ' . wordwrap($msg, 66, "\n   ", true))->blank()
            ->pipe('|08   [|15L|08] SysOp / staff log in      ·      reload the page to retry');
        return $engine->finishPublic($f);
    }

    /** POST /api/action - one keystroke / form submit / command. */
    public function action(Request $req): Response
    {
        $session = Session::start($req);
        if (!$this->csrfOk($req, $session)) {
            return Response::error('Stale session token. Reconnect.', 419);
        }

        $engine = new Engine($session, $req);

        if (Config::bool('maintenance', false) && $engine->rank() < 80) {
            $type = $engine->currentType();
            $k = strtoupper((string) $req->input('key', ''));
            if ($type === 'auth' || $type === 'changepw' || $type === 'motd') {
                // let the login flow run untouched
            } elseif ($k === 'L') {
                $engine->replaceStack([['t' => 'auth', 'st' => ['step' => 'login']]]);
                $session->save();
                return Response::json($engine->finishPublic($engine->renderCurrentFrame()));
            } else {
                return Response::json($this->busyFrame($session, $engine));
            }
        }

        $in = [
            'key'   => (string) $req->input('key', ''),
            'input' => (string) $req->input('input', ''),
            'cmd'   => (string) $req->input('cmd', ''),
            'data'  => (array) $req->input('data', []),
        ];

        // deep-link jump requested by the shell after boot
        $goto = (string) $req->input('goto', '');
        if ($goto !== '' && $in['cmd'] === 'goto') {
            $frame = $this->handleGoto($engine, $goto);
        } else {
            $frame = $engine->dispatch($in);
        }
        $session->save();

        return Response::json($frame);
    }

    public function whoami(Request $req): Response
    {
        $session = Session::start($req);
        $engine  = new Engine($session, $req);
        return Response::json([
            'guest'  => $session->isGuest(),
            'handle' => $session->handle(),
            'rank'   => $engine->rank(),
            'node'   => $session->node,
            'phone'  => $session->ipPhone,
            'csrf'   => $session->csrf(),
            'frame'  => $engine->render(),
        ]);
    }

    public function logout(Request $req): Response
    {
        $session = Session::start($req);
        if (!$this->csrfOk($req, $session)) {
            return Response::error('Stale session token.', 419);
        }
        $frame = (new Engine($session, $req))->dispatch(['key' => 'Y', 'cmd' => '', 'via' => 'logout']);
        // ensure logoff even if not on logoff node
        return Response::json($frame);
    }

    /**
     * GET /api/ticker - the bottom status crawl. Fully SysOp-controlled through
     * the `settings` table (edit in SysOp -> Global Config):
     *   ticker_sources        csv of custom,news,oneliners (order = display order)
     *   ticker_custom         free text, one message per line
     *   ticker_news_count     how many latest headlines (0 = none)
     *   ticker_oneliner_count how many latest one-liners (0 = none)
     *   ticker_speed_secs     seconds for one full loop (lower = faster)
     */
    public function ticker(Request $req): Response
    {
        $sources = array_filter(array_map('trim', explode(',', Config::setting('ticker_sources', 'custom,news,oneliners'))));
        $lines = [];

        foreach ($sources as $src) {
            if ($src === 'custom') {
                foreach (preg_split('/[\r\n]+/', Config::setting('ticker_custom', '')) ?: [] as $l) {
                    $l = trim($l);
                    if ($l !== '') {
                        $lines[] = $l;
                    }
                }
            } elseif ($src === 'news') {
                $n = max(0, Config::int('ticker_news_count', 3));
                if ($n > 0) {
                    foreach (Db::all('SELECT title FROM news_items ORDER BY published_at DESC, id DESC LIMIT ' . $n) as $r) {
                        $lines[] = 'NEWS: ' . $r['title'];
                    }
                }
            } elseif ($src === 'oneliners') {
                $n = max(0, Config::int('ticker_oneliner_count', 10));
                if ($n > 0) {
                    foreach (Db::all('SELECT body FROM oneliners WHERE deleted_at IS NULL ORDER BY id DESC LIMIT ' . $n) as $r) {
                        $lines[] = $r['body'];
                    }
                }
            }
        }

        if (!$lines) {
            $lines[] = Config::setting('site_tagline', '') ?: ('Welcome to ' . Config::setting('site_name', 'THUGS(red) BBS'));
        }

        return Response::json([
            'lines' => $lines,
            'speed' => max(20, Config::int('ticker_speed_secs', 60)),
        ])->withHeader('Cache-Control', 'public, max-age=20');
    }

    /** GET /api/file/{id} - authenticated, audited download. */
    public function download(Request $req, array $args): Response
    {
        $session = Session::start($req);
        $file = Db::one('SELECT f.*, a.min_read_rank FROM files f JOIN file_areas a ON a.id = f.area_id
                         WHERE f.id = ? AND f.deleted_at IS NULL AND f.is_approved = 1', [(int) $args['id']]);
        if (!$file) {
            return Response::error('File not found.', 404);
        }
        $rank = (new Engine($session, $req))->rank();
        if ($rank < (int) $file['min_read_rank']) {
            return Response::error('You do not have clearance for this area.', 403);
        }
        if ($file['external_url']) {
            return Response::redirect($file['external_url']);
        }
        $path = \Bbs\Core\Storage::filesPath($file['storage_path']);
        if (!is_file($path)) {
            return Response::error('File missing from storage.', 410);
        }
        Db::q('UPDATE files SET downloads = downloads + 1 WHERE id = ?', [$file['id']]);
        if ($session->userId) {
            Db::q('UPDATE users SET downloads = downloads + 1 WHERE id = ?', [$session->userId]);
        }
        AuditLog::bind($session);
        AuditLog::record('file.download', 'file', (int) $file['id'], $file['filename']);

        return Response::raw(
            (string) file_get_contents($path),
            'application/octet-stream'
        )->withHeader('Content-Disposition', 'attachment; filename="' . addslashes($file['filename']) . '"')
         ->withHeader('Content-Length', (string) $file['size_bytes'])
         ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    // -----------------------------------------------------------------
    private function csrfOk(Request $req, Session $session): bool
    {
        $token = $req->header('x-csrf') ?? (string) $req->input('csrf', '');
        // A brand-new session (first POST before any GET) has a valid token too.
        return Crypto::csrfCheck($session->id, $token) || $session->isNew;
    }

    private function handleGoto(Engine $engine, string $goto): array
    {
        // goto formats: board:slug, msg:id, user:handle, news:cat, game:slug
        [$kind, $ref] = array_pad(explode(':', $goto, 2), 2, '');
        $frame = match ($kind) {
            'board' => $engine->goModule('msg.read', ['board_slug' => $ref]),
            'msg'   => $engine->goModule('msg.read', ['message_id' => (int) $ref]),
            'user'  => $engine->goModule('users.list', ['handle' => $ref]),
            'news'  => $engine->goModule('news.' . preg_replace('/[^a-z]/', '', $ref)),
            'game'  => $engine->goModule('game.list', ['play' => $ref]),
            default => $engine->renderCurrentFrame(),
        };
        return $engine->finishPublic($frame);
    }
}
