<?php

declare(strict_types=1);

namespace Bbs\Auth;

use Bbs\Core\Cache;
use Bbs\Core\Config;
use Bbs\Core\Crypto;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Bbs\PhoneNumber;

/**
 * Database-backed session ("the call"). One row in `sessions`, mirrored into
 * Redis for fast reads. A fresh anonymous session is created for every visitor;
 * login() rotates the id and attaches a user.
 */
final class Session
{
    private bool $dirty = false;
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,mixed>|null */
    private ?array $userRow = null;
    private bool $userLoaded = false;

    private function __construct(
        public string $id,
        public ?int $userId,
        public string $ip,
        public string $ipPhone,
        public int $node,
        array $data,
        public bool $isNew,
    ) {
        $this->data = $data;
    }

    public static function start(Request $req): self
    {
        $cookieName = (string) Config::get('session.cookie', 'bbs_node');
        $sid   = $_COOKIE[$cookieName] ?? '';
        $ip    = $req->ip();
        $phone = PhoneNumber::fromIp($ip);
        $now   = time();

        $row = null;
        if (preg_match('/^[a-f0-9]{64}$/', $sid)) {
            $row = self::loadRow($sid);
        }

        $valid = false;
        if ($row) {
            $expOk = strtotime($row['expires_at']) > $now;
            $netOk = !Config::bool('session.bind_network', true)
                || hash_equals($row['net_hash'], self::netHash($ip));
            $valid = $expOk && $netOk;
        }

        if ($valid) {
            $s = new self(
                $row['id'],
                $row['user_id'] !== null ? (int) $row['user_id'] : null,
                $ip,
                $phone,
                (int) $row['node'],
                self::decodeData($row['data'] ?? ''),
                false,
            );
            $s->touch($req);
            return $s;
        }

        // create fresh
        $newId = bin2hex(random_bytes(32));
        $node  = self::assignNode();
        $idle  = Config::int('session.idle_ttl', 3600);
        Db::insert('sessions', [
            'id'           => $newId,
            'user_id'      => null,
            'ip'           => $ip,
            'ip_phone'     => $phone,
            'net_hash'     => self::netHash($ip),
            'user_agent'   => $req->userAgent(),
            'node'         => $node,
            'data'         => '{}',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', $now + $idle),
        ]);

        self::sendCookie($newId, $req);

        return new self($newId, null, $ip, $phone, $node, [], true);
    }

    // -----------------------------------------------------------------
    private static function loadRow(string $sid): ?array
    {
        $cached = Cache::get('sess:' . $sid);
        if ($cached !== null) {
            $row = json_decode($cached, true);
            if (is_array($row)) {
                return $row;
            }
        }
        $row = Db::one('SELECT * FROM sessions WHERE id = ?', [$sid]);
        if ($row) {
            Cache::set('sess:' . $sid, json_encode($row), 60);
        }
        return $row;
    }

    private static function assignNode(): int
    {
        $max   = max(1, Config::int('nodes', Config::int('terminal.nodes', 8)));
        $inUse = array_map(
            'intval',
            array_column(
                Db::all("SELECT DISTINCT node FROM sessions WHERE expires_at > NOW() AND node > 0"),
                'node'
            )
        );
        for ($n = 1; $n <= $max; $n++) {
            if (!in_array($n, $inUse, true)) {
                return $n;
            }
        }
        return random_int(1, $max); // board full - double up, the boot screen warns about it
    }

    public static function nodesFree(): int
    {
        $max  = max(1, Config::int('nodes', Config::int('terminal.nodes', 8)));
        $used = (int) Db::val("SELECT COUNT(DISTINCT node) FROM sessions WHERE expires_at > NOW() AND node > 0");
        return max(0, $max - $used);
    }

    private static function netHash(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = array_slice(explode(':', $ip), 0, 4);
            $net   = implode(':', $parts);
        } else {
            $o   = explode('.', $ip);
            $net = ($o[0] ?? '0') . '.' . ($o[1] ?? '0') . '.' . ($o[2] ?? '0');
        }
        return hash_hmac('sha256', 'net|' . $net, Crypto::hmac('net-bind'));
    }

    private static function sendCookie(string $id, Request $req): void
    {
        setcookie((string) Config::get('session.cookie', 'bbs_node'), $id, [
            'expires'  => time() + Config::int('session.absolute_ttl', 604800),
            'path'     => '/',
            'secure'   => $req->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[(string) Config::get('session.cookie', 'bbs_node')] = $id;
    }

    private function touch(Request $req): void
    {
        $now  = time();
        $idle = Config::int('session.idle_ttl', 3600);
        Db::update('sessions', [
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', $now + $idle),
            'ip'           => $req->ip(),
            'ip_phone'     => $this->ipPhone,
        ], ['id' => $this->id]);
        Cache::del('sess:' . $this->id);
    }

    // -----------------------------------------------------------------
    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->userLoaded) {
            return $this->userRow;
        }
        $this->userLoaded = true;
        if ($this->userId === null) {
            return $this->userRow = null;
        }
        $this->userRow = Db::one(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL',
            [$this->userId]
        );
        return $this->userRow;
    }

    public function handle(): string
    {
        return $this->user()['handle'] ?? 'guest';
    }

    public function login(int $userId): void
    {
        // rotate id to prevent fixation
        $oldId = $this->id;
        $newId = bin2hex(random_bytes(32));
        Db::update('sessions', [
            'id'      => $newId,
            'user_id' => $userId,
        ], ['id' => $oldId]);
        Cache::del('sess:' . $oldId);
        $this->id         = $newId;
        $this->userId     = $userId;
        $this->userLoaded = false;
        $this->userRow    = null;
        $req = Request::capture();
        self::sendCookie($newId, $req);
    }

    public function logout(): void
    {
        Db::q('DELETE FROM sessions WHERE id = ?', [$this->id]);
        Db::q('DELETE FROM chat_presence WHERE session_id = ?', [$this->id]);
        Cache::del('sess:' . $this->id);
        $this->userId     = null;
        $this->userRow    = null;
        $this->userLoaded = true;
        $this->data       = [];
    }

    // ---- per-session scratch data (JSON column) --------------------
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
        $this->dirty = true;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function save(): void
    {
        if (!$this->dirty) {
            return;
        }
        Db::update('sessions', [
            'data' => json_encode($this->data, JSON_UNESCAPED_SLASHES),
        ], ['id' => $this->id]);
        Cache::del('sess:' . $this->id);
        $this->dirty = false;
    }

    public function csrf(): string
    {
        return Crypto::csrfToken($this->id);
    }

    private static function decodeData(string $json): array
    {
        $d = json_decode($json ?: '{}', true);
        return is_array($d) ? $d : [];
    }

    /** Housekeeping - call from cron. */
    public static function gc(): int
    {
        return Db::q('DELETE FROM sessions WHERE expires_at < NOW() - INTERVAL 1 DAY')->rowCount();
    }
}
