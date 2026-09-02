<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Symmetric encryption (libsodium secretbox) for PII stored in `user_secrets`,
 * plus HMAC signing and CSRF token helpers.
 */
final class Crypto
{
    private static ?string $key = null;

    private static function key(): string
    {
        if (self::$key === null) {
            $raw = base64_decode((string) Config::get('crypto.sodium_key', ''), true);
            if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new \RuntimeException('crypto.sodium_key missing or not 32 bytes (base64).');
            }
            self::$key = $raw;
        }
        return self::$key;
    }

    /** Encrypt a string, returns base64(nonce . ciphertext). */
    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, self::key());
        return base64_encode($nonce . $cipher);
    }

    /** Decrypt base64(nonce . ciphertext); returns null on tamper/failure. */
    public static function decrypt(string $blob): ?string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }
        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        return $plain === false ? null : $plain;
    }

    public static function hmac(string $data): string
    {
        $k = base64_decode((string) Config::get('crypto.app_key', ''), true) ?: 'insecure-dev-key';
        return hash_hmac('sha256', $data, $k);
    }

    /** Deterministic blind index so we can look up an encrypted email without decrypting every row. */
    public static function blindIndex(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), self::hmac('blind-index-salt'));
    }

    public static function csrfToken(string $sessionId): string
    {
        $salt = (string) Config::get('crypto.csrf_salt', 'salt');
        return hash_hmac('sha256', 'csrf|' . $sessionId, $salt);
    }

    public static function csrfCheck(string $sessionId, ?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken($sessionId), $token);
    }
}
