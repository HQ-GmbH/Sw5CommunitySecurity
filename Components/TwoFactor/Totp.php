<?php

declare(strict_types=1);

/*
 * This file is part of Sw5CommunitySecurity.
 *
 * (c) HQ GmbH <https://www.hqgmbh.de>
 *
 * Licensed under the GNU General Public License v3.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Sw5CommunitySecurity\Components\TwoFactor;

/**
 * Time-based one-time passwords (RFC 6238 with HMAC-SHA1, RFC 4226), the
 * defaults of common authenticator apps: 30-second steps, 6 digits.
 */
final class Totp
{
    public const STEP_SECONDS = 30;

    public const DIGITS = 6;

    /**
     * Accepted clock drift in steps (one step before and after).
     */
    public const WINDOW = 1;

    /**
     * 160-bit secret as recommended by RFC 4226, Base32-encoded.
     */
    public static function generateSecret(): string
    {
        return Base32::encode(random_bytes(20));
    }

    public static function step(int $timestamp): int
    {
        return intdiv($timestamp, self::STEP_SECONDS);
    }

    /**
     * @param string $secret binary secret
     */
    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = \ord($hash[19]) & 0x0F;
        $binary = ((\ord($hash[$offset]) & 0x7F) << 24)
            | (\ord($hash[$offset + 1]) << 16)
            | (\ord($hash[$offset + 2]) << 8)
            | \ord($hash[$offset + 3]);

        return str_pad((string) ($binary % 10 ** $digits), $digits, '0', \STR_PAD_LEFT);
    }

    /**
     * Returns the matching step, or null. Steps up to $lastUsedStep are
     * rejected, so a code can be used only once.
     *
     * @param string $base32Secret secret as stored and shown to the user
     */
    public static function verify(string $base32Secret, string $code, int $timestamp, ?int $lastUsedStep = null): ?int
    {
        $code = str_replace([' ', '-'], '', $code);
        $secret = Base32::decode($base32Secret);
        if ($secret === null || $secret === '' || preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }

        $current = self::step($timestamp);
        $matched = null;
        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; ++$step) {
            // Compare every candidate to keep the timing independent of the match.
            if (hash_equals(self::code($secret, $step), $code) && ($lastUsedStep === null || $step > $lastUsedStep)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /**
     * otpauth URI for manual entry or QR codes (Key Uri Format of common
     * authenticator apps).
     */
    public static function uri(string $base32Secret, string $issuer, string $account): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $base32Secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::STEP_SECONDS;
    }
}
