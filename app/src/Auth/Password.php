<?php

declare(strict_types=1);

namespace Bbs\Auth;

/**
 * Password hashing. Weak passwords are allowed by design (this is a nostalgia
 * BBS) - we only enforce a 3-char minimum - but everything is still bcrypt.
 */
final class Password
{
    private const ALGO = PASSWORD_BCRYPT;
    private const OPTS = ['cost' => 12];

    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 200;

    public static function hash(string $plain): string
    {
        return password_hash($plain, self::ALGO, self::OPTS);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGO, self::OPTS);
    }

    /** @return string|null  null = OK, otherwise a human-readable reason */
    public static function validationError(string $plain): ?string
    {
        $len = strlen($plain);
        if ($len < self::MIN_LENGTH) {
            return 'Password too short (minimum ' . self::MIN_LENGTH . ' characters).';
        }
        if ($len > self::MAX_LENGTH) {
            return 'Password too long.';
        }
        return null;
    }
}
