<?php

declare(strict_types=1);

namespace Bbs\Bbs;

/**
 * Turns a caller's IP address into a stable, plausible-looking North-American
 * phone number - "(AAA) EEE-LLLL" - so every "dial in" screen can pretend the
 * visitor phoned the board. Deterministic: same IP always yields the same
 * number. Nothing security-sensitive depends on this; it's for flavour.
 */
final class PhoneNumber
{
    public static function fromIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '0.0.0.0') {
            return '(000) 000-0000';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            [$a, $b, $c, $d] = array_map('intval', explode('.', $ip));
            $area = 200 + (($a * 5 + $b * 3 + 7) % 800);           // 200-999
            $exch = 200 + ((($c * 7) + ($d * 13) + 11) % 800);     // 200-999
            $line = (int) (sprintf('%u', crc32($ip)) % 10000);     // 0000-9999
            return sprintf('(%03d) %03d-%04d', $area, $exch, $line);
        }

        // IPv6 (or anything else): fold to 10 digits.
        $h = sprintf('%u', crc32($ip)) . sprintf('%u', crc32(strrev($ip)));
        $h = str_pad(substr($h, 0, 10), 10, '0');
        return sprintf('(%s) %s-%s', substr($h, 0, 3), substr($h, 3, 3), substr($h, 6, 4));
    }

    /** Just the digits, for the DTMF dialer in the boot sequence. */
    public static function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /** A fake "node line" number, e.g. NODE 3 -> 555-0103 style. */
    public static function nodeLine(int $node): string
    {
        return sprintf('555-%04d', 100 + $node);
    }
}
