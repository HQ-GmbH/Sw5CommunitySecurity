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

namespace Sw5CommunitySecurity\Components\Config;

/**
 * Typed, validated view on the raw plugin configuration of one shop.
 *
 * Every value is normalised against a whitelist, so nothing from the database
 * reaches a response header unchecked. Unknown or malformed values fall back
 * to the defaults declared in Resources/config.xml.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class PluginConfig
{
    public const OFF = 'off';

    public const X_FRAME_OPTIONS_VALUES = ['SAMEORIGIN', 'DENY', self::OFF];

    public const REFERRER_POLICY_VALUES = [
        'strict-origin-when-cross-origin',
        'strict-origin',
        'same-origin',
        'no-referrer',
        self::OFF,
    ];

    /** Upper bound for HSTS max-age: two years, the value hstspreload.org recommends. */
    public const HSTS_MAX_AGE_LIMIT = 63072000;

    private function __construct(
        public readonly bool $securityHeadersEnabled,
        public readonly bool $securityHeadersInBackend,
        public readonly bool $xContentTypeOptions,
        public readonly string $xFrameOptions,
        public readonly string $referrerPolicy,
        public readonly int $hstsMaxAge,
        public readonly bool $hstsIncludeSubDomains,
    ) {
    }

    /**
     * @param array<string, mixed> $raw values as returned by the Shopware plugin configuration reader
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            self::toBool($raw['securityHeadersEnabled'] ?? null, true),
            self::toBool($raw['securityHeadersInBackend'] ?? null, false),
            self::toBool($raw['headerXContentTypeOptions'] ?? null, true),
            self::toChoice($raw['headerXFrameOptions'] ?? null, self::X_FRAME_OPTIONS_VALUES, 'SAMEORIGIN'),
            self::toChoice($raw['headerReferrerPolicy'] ?? null, self::REFERRER_POLICY_VALUES, 'strict-origin-when-cross-origin'),
            self::toHstsMaxAge($raw['headerHstsMaxAge'] ?? null),
            self::toBool($raw['headerHstsIncludeSubDomains'] ?? null, false),
        );
    }

    private static function toBool(mixed $value, bool $default): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        if (\is_string($value)) {
            $parsed = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }

    /**
     * @param list<string> $allowed
     */
    private static function toChoice(mixed $value, array $allowed, string $default): string
    {
        if (\is_string($value) && \in_array($value, $allowed, true)) {
            return $value;
        }

        return $default;
    }

    private static function toHstsMaxAge(mixed $value): int
    {
        if (\is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (\is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!\is_int($value) || $value <= 0) {
            return 0;
        }

        return min($value, self::HSTS_MAX_AGE_LIMIT);
    }
}
