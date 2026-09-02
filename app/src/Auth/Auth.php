<?php

declare(strict_types=1);

namespace Bbs\Auth;

use Bbs\Admin\AuditLog;
use Bbs\Core\Config;
use Bbs\Core\Crypto;
use Bbs\Core\Db;
use Bbs\Core\Queue;

/**
 * Registration & login. Weak passwords are allowed (see Password); brute force
 * is contained with a sliding-window attempt counter per handle and per IP.
 */
final class Auth
{
    private const WINDOW_SECS   = 900;   // 15 min
    private const MAX_PER_IP     = 30;
    private const MAX_PER_HANDLE = 10;

    /** @return array{ok:bool,error?:string,user?:array,must_change?:bool} */
    public static function login(Session $session, string $handle, string $password): array
    {
        $handle = trim($handle);
        $ip     = $session->ip;

        if (self::rateLimited($handle, $ip)) {
            self::logAttempt($handle, $ip, false);
            return ['ok' => false, 'error' => 'Too many attempts. Wait a few minutes and redial.'];
        }

        $user = Db::one(
            'SELECT * FROM users WHERE handle = ? AND deleted_at IS NULL',
            [$handle]
        );

        if (!$user || !Password::verify($password, $user['password_hash'])) {
            self::logAttempt($handle, $ip, false);
            return ['ok' => false, 'error' => 'Bad handle or password.'];
        }

        if ($user['status'] === 'banned') {
            self::logAttempt($handle, $ip, false);
            return ['ok' => false, 'error' => 'This account has been banned. Contact the SysOp.'];
        }
        if ($user['status'] === 'suspended') {
            self::logAttempt($handle, $ip, false);
            return ['ok' => false, 'error' => 'This account is suspended.'];
        }
        if ($user['status'] === 'pending') {
            self::logAttempt($handle, $ip, false);
            return ['ok' => false, 'error' => 'Your application is still awaiting SysOp validation.'];
        }

        if (Password::needsRehash($user['password_hash'])) {
            Db::update('users', ['password_hash' => Password::hash($password)], ['id' => $user['id']]);
        }

        self::logAttempt($handle, $ip, true);
        $session->login((int) $user['id']);

        Db::update('users', [
            'last_login_at'    => date('Y-m-d H:i:s'),
            'last_login_ip'    => $ip,
            'last_login_phone' => $session->ipPhone,
            'calls'            => (int) $user['calls'] + 1,
        ], ['id' => $user['id']]);

        AuditLog::bind($session);
        AuditLog::record('auth.login', 'user', (int) $user['id'], "$handle logged in");

        return [
            'ok'          => true,
            'user'        => $user,
            'must_change' => (bool) $user['must_change_password'],
        ];
    }

    /** @return array{ok:bool,error?:string,user_id?:int,pending?:bool} */
    public static function register(Session $session, string $handle, string $password, string $email = ''): array
    {
        if (!Config::bool('registration_open', true)) {
            return ['ok' => false, 'error' => 'New user registration is closed right now.'];
        }

        $handle = trim($handle);
        if (!preg_match('/^[A-Za-z0-9 _.\-]{2,32}$/', $handle)) {
            return ['ok' => false, 'error' => 'Handle must be 2-32 chars: letters, digits, space, _ . -'];
        }
        if (preg_match('/^(all|sysop|system|guest|anonymous|admin)$/i', $handle)
            && strcasecmp($handle, (string) Config::get('sysop.handle', 'sysop')) !== 0) {
            return ['ok' => false, 'error' => 'That handle is reserved.'];
        }
        if (Db::val('SELECT 1 FROM users WHERE handle = ?', [$handle])) {
            return ['ok' => false, 'error' => 'That handle is taken. Pick another.'];
        }
        if ($err = Password::validationError($password)) {
            return ['ok' => false, 'error' => $err];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'That e-mail address looks wrong. Leave it blank if you like.'];
        }

        $defaultRole = (string) Config::get('new_user_role', 'user');

        $uid = Db::tx(function () use ($handle, $password, $email, $defaultRole, $session) {
            $uid = Db::insert('users', [
                'handle'        => $handle,
                'password_hash' => Password::hash($password),
                'status'        => 'active',
                'last_login_ip' => $session->ip,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            Rbac::grant($uid, $defaultRole);
            if ($email !== '') {
                Db::insert('user_secrets', [
                    'user_id'      => $uid,
                    'email_cipher' => Crypto::encrypt($email),
                    'email_index'  => Crypto::blindIndex($email),
                ]);
            }
            return $uid;
        });

        self::logAttempt($handle, $session->ip, true);
        $session->login((int) $uid);

        Db::update('users', [
            'last_login_at'    => date('Y-m-d H:i:s'),
            'last_login_phone' => $session->ipPhone,
            'calls'            => 1,
        ], ['id' => $uid]);

        AuditLog::bind($session);
        AuditLog::record('user.register', 'user', (int) $uid, "$handle registered");
        Queue::push(Queue::TUBE_DISCORD, [
            'event'   => 'user.register',
            'handle'  => $handle,
            'ip_phone' => $session->ipPhone,
        ]);

        return ['ok' => true, 'user_id' => (int) $uid];
    }

    public static function changePassword(Session $session, string $current, string $new): array
    {
        $user = $session->user();
        if (!$user) {
            return ['ok' => false, 'error' => 'Not logged in.'];
        }
        // If forced change, allow skipping the "current" check only when it's still the seeded one.
        $forced = (bool) $user['must_change_password'];
        if (!$forced && !Password::verify($current, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is wrong.'];
        }
        if ($err = Password::validationError($new)) {
            return ['ok' => false, 'error' => $err];
        }
        Db::update('users', [
            'password_hash'        => Password::hash($new),
            'must_change_password' => 0,
        ], ['id' => $user['id']]);
        AuditLog::bind($session);
        AuditLog::record('auth.password_change', 'user', (int) $user['id'], 'password changed');
        return ['ok' => true];
    }

    // -----------------------------------------------------------------
    public static function rateLimited(string $handle, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECS);
        $byIp = (int) Db::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ok = 0 AND created_at > ?',
            [$ip, $since]
        );
        if ($byIp >= self::MAX_PER_IP) {
            return true;
        }
        $byHandle = (int) Db::val(
            'SELECT COUNT(*) FROM login_attempts WHERE handle = ? AND ok = 0 AND created_at > ?',
            [$handle, $since]
        );
        return $byHandle >= self::MAX_PER_HANDLE;
    }

    private static function logAttempt(string $handle, string $ip, bool $ok): void
    {
        Db::insert('login_attempts', [
            'handle'     => mb_substr($handle, 0, 64),
            'ip'         => $ip,
            'ok'         => $ok ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
