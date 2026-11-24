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

namespace Sw5CommunitySecurity\Components\SecurityHeaders;

/**
 * Validates free-text header values from the plugin configuration.
 *
 * Values that do not match the expected syntax are rejected as a whole
 * (null): a header is better left out than sent half-parsed. Only printable
 * ASCII is accepted, so line breaks can never split a header.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class HeaderValueNormalizer
{
    /**
     * Upper bound for a policy, well below common web server header limits (8 KB).
     */
    public const MAX_LENGTH = 4096;

    /**
     * Normalises a Content-Security-Policy (CSP Level 3, "serialized-policy").
     * Directives are separated by ";"; a "," would start a second policy and
     * is rejected, as is a directive named twice.
     */
    public static function contentSecurityPolicy(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || \strlen($value) > self::MAX_LENGTH || preg_match('/^[\x20-\x7E]+$/', $value) !== 1
            || strpos($value, ',') !== false) {
            return null;
        }

        $directives = [];
        foreach (explode(';', $value) as $directive) {
            $tokens = preg_split('/ +/', trim($directive), -1, \PREG_SPLIT_NO_EMPTY);
            if ($tokens === false || $tokens === []) {
                continue;
            }

            $name = strtolower(array_shift($tokens));
            if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1 || isset($directives[$name])) {
                return null;
            }

            $directives[$name] = trim($name . ' ' . implode(' ', $tokens));
        }

        return $directives === [] ? null : implode('; ', $directives);
    }

    /**
     * Accepts an absolute https:// URL for the "report-uri" directive.
     */
    public static function reportUri(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || \strlen($value) > self::MAX_LENGTH
            || preg_match('#^https://[\x21-\x7E]+$#', $value) !== 1
            || preg_match('/[;,\'"<>\\\\]/', $value) === 1
            || filter_var($value, \FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    /**
     * Normalises a Permissions-Policy (structured-field dictionary), e.g.
     * "camera=(), microphone=(self)". Allowlist members are "*", self, src
     * or quoted origins.
     */
    public static function permissionsPolicy(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || \strlen($value) > self::MAX_LENGTH || preg_match('/^[\x20-\x7E]+$/', $value) !== 1) {
            return null;
        }

        $member = '(?:\*|self|src|"https?:\/\/[a-zA-Z0-9.:\-\[\]*]+")';
        $allowlist = '(?:\*|self|src|\((?: *' . $member . ')* *\))';

        $features = [];
        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);
            if (preg_match('/^([a-z][a-z0-9-]*) *= *(' . $allowlist . ')$/', $entry, $match) !== 1
                || isset($features[$match[1]])) {
                return null;
            }

            $allowlistValue = $match[2];
            if ($allowlistValue[0] === '(') {
                preg_match_all('/' . $member . '/', $allowlistValue, $members);
                $allowlistValue = '(' . implode(' ', $members[0]) . ')';
            }

            $features[$match[1]] = $match[1] . '=' . $allowlistValue;
        }

        return implode(', ', $features);
    }
}
