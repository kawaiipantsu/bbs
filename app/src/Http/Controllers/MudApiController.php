<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Auth\Auth;
use Bbs\Auth\Session;
use Bbs\Core\Config;
use Bbs\Core\Crypto;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Core\Response;
use Bbs\Mud\Api;
use Bbs\Mud\Player;

/**
 * JSON API for the standalone graphical MUD client at /hackers-mud.
 * Authenticates against the same BBS user table (Auth::login), keeps a small
 * per-session log buffer, and returns structured world snapshots.
 */
final class MudApiController
{
    private const LOG_KEY = 'mudc_log';
    private const LOG_CAP = 200;

    public function whoami(Request $req): Response
    {
        $s = Session::start($req);
        $uid = $s->userId;
        $hasChar = $uid ? (bool) Db::val('SELECT 1 FROM mud_players WHERE user_id = ?', [$uid]) : false;
        return $this->json([
            'authed'  => $uid !== null && !$s->isGuest(),
            'handle'  => $s->handle(),
            'csrf'    => $s->csrf(),
            'hasChar' => $hasChar,
            'maint'   => Config::bool('maintenance', false),
        ]);
    }

    public function login(Request $req): Response
    {
        $s = Session::start($req);
        $handle = trim((string) $req->input('handle', ''));
        $pass   = (string) $req->input('password', '');
        if ($handle === '' || $pass === '') {
            return $this->json(['ok' => false, 'error' => 'Handle and password, please.'], 200);
        }
        $res = Auth::login($s, $handle, $pass);
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'error' => $res['error'] ?? 'Login failed.'], 200);
        }
        $s->save();
        $uid = (int) $s->userId;
        $hasChar = (bool) Db::val('SELECT 1 FROM mud_players WHERE user_id = ?', [$uid]);
        return $this->json([
            'ok'           => true,
            'csrf'         => $s->csrf(),
            'handle'       => $s->handle(),
            'needArchetype' => !$hasChar,
            'archetypes'   => $hasChar ? null : Api::archetypes(),
        ]);
    }

    public function archetype(Request $req): Response
    {
        $s = Session::start($req);
        if (!$this->csrfOk($req, $s) || $s->userId === null || $s->isGuest()) {
            return $this->json(['ok' => false, 'error' => 'Not signed in.'], 200);
        }
        $uid = (int) $s->userId;
        if (Db::val('SELECT 1 FROM mud_players WHERE user_id = ?', [$uid])) {
            return $this->json(['ok' => true, 'created' => false]);
        }
        $choice = (string) $req->input('choice', '');
        $res = Api::chooseArchetype($uid, $s->handle() ?: 'runner', $choice);
        if (empty($res['done'])) {
            return $this->json(['ok' => false, 'error' => 'Pick 1, 2 or 3.'], 200);
        }
        $this->setLog($s, ["|10Jacked in for the first time. Welcome to Night City."]);
        $pid = (int) $res['player_id'];
        return $this->json(['ok' => true, 'created' => true, 'sfx' => $res['sfx'] ?? [],
                            'state' => Api::snapshot($pid, $this->getLog($s))]);
    }

    public function state(Request $req): Response
    {
        [$s, $pid, $err] = $this->playerFor($req);
        if ($err) {
            return $err;
        }
        return $this->json(['ok' => true, 'state' => Api::snapshot($pid, $this->getLog($s))]);
    }

    public function cmd(Request $req): Response
    {
        [$s, $pid, $err] = $this->playerFor($req, true);
        if ($err) {
            return $err;
        }
        $cmd = trim((string) $req->input('cmd', ''));
        if (mb_strlen($cmd) > 200) {
            $cmd = mb_substr($cmd, 0, 200);
        }
        $log = $this->getLog($s);
        if ($cmd !== '') {
            $log[] = '|08> |07' . $cmd;
        }
        $res = Api::run($pid, $cmd);
        $log = array_merge($log, $res['lines']);
        $this->setLog($s, $log);

        return $this->json([
            'ok'    => true,
            'lines' => $res['lines'],
            'sfx'   => $res['sfx'],
            'state' => Api::snapshot($pid, $this->getLog($s)),
        ]);
    }

    public function logout(Request $req): Response
    {
        $s = Session::start($req);
        $s->forget(self::LOG_KEY);
        $s->logout();
        return $this->json(['ok' => true]);
    }

    /* ---- helpers ------------------------------------------------- */

    /** @return array{0:Session,1:int,2:?Response} */
    private function playerFor(Request $req, bool $post = false): array
    {
        $s = Session::start($req);
        if ($post && !$this->csrfOk($req, $s)) {
            return [$s, 0, $this->json(['ok' => false, 'error' => 'Stale token. Reload.'], 419)];
        }
        if ($s->userId === null || $s->isGuest()) {
            return [$s, 0, $this->json(['ok' => false, 'error' => 'auth', 'authed' => false], 200)];
        }
        if (Config::bool('maintenance', false) && \Bbs\Auth\Rbac::rank($s->user()) < 80) {
            return [$s, 0, $this->json(['ok' => false, 'error' => 'The grid is down for maintenance.'], 200)];
        }
        $pid = (int) Db::val('SELECT id FROM mud_players WHERE user_id = ?', [(int) $s->userId]);
        if (!$pid) {
            return [$s, 0, $this->json(['ok' => false, 'error' => 'nochar', 'needArchetype' => true,
                                        'archetypes' => Api::archetypes()], 200)];
        }
        return [$s, $pid, null];
    }

    private function csrfOk(Request $req, Session $s): bool
    {
        $token = $req->header('x-csrf') ?? (string) $req->input('csrf', '');
        return Crypto::csrfCheck($s->id, $token) || $s->isNew;
    }

    /** @return list<string> */
    private function getLog(Session $s): array
    {
        $l = $s->get(self::LOG_KEY, []);
        return is_array($l) ? $l : [];
    }

    private function setLog(Session $s, array $log): void
    {
        $s->put(self::LOG_KEY, array_values(array_slice($log, -self::LOG_CAP)));
        $s->save();
    }

    private function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status)->noCache();
    }
}
