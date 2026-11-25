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
 * Base32 without padding (RFC 4648, section 6), the format authenticator
 * apps expect for TOTP secrets.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(\ord($char)), 8, '0', \STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $binary === '' ? '' : $encoded;
    }

    /**
     * Accepts lower case, spaces and padding; returns null for other characters.
     */
    public static function decode(string $encoded): ?string
    {
        $encoded = strtoupper(str_replace([' ', '='], '', $encoded));
        $bits = '';
        foreach (str_split($encoded) as $char) {
            if ($char === '') {
                continue;
            }

            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return null;
            }

            $bits .= str_pad(decbin($value), 5, '0', \STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (\strlen($byte) === 8) {
                $binary .= \chr((int) bindec($byte));
            }
        }

        return $binary;
    }
}
