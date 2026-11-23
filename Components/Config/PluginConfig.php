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

    /**
     * Upper bound for HSTS max-age: two years, the value hstspreload.org recommends.
     */
    public const HSTS_MAX_AGE_LIMIT = 63072000;

    /**
     * @readonly
     */
    public bool $securityHeadersEnabled;

    /**
     * @readonly
     */
    public bool $securityHeadersInBackend;

    /**
     * @readonly
     */
    public bool $xContentTypeOptions;

    /**
     * @readonly
     */
    public string $xFrameOptions;

    /**
     * @readonly
     */
    public string $referrerPolicy;

    /**
     * @readonly
     */
    public int $hstsMaxAge;

    /**
     * @readonly
     */
    public bool $hstsIncludeSubDomains;

    /**
     * @readonly
     */
    public bool $templateGuardEnabled;

    /**
     * @readonly
     */
    public bool $templateGuardStrictIncludeMath;

    /**
     * @readonly
     */
    public bool $uploadGuardEnabled;

    private function __construct(bool $securityHeadersEnabled, bool $securityHeadersInBackend, bool $xContentTypeOptions, string $xFrameOptions, string $referrerPolicy, int $hstsMaxAge, bool $hstsIncludeSubDomains, bool $templateGuardEnabled, bool $templateGuardStrictIncludeMath, bool $uploadGuardEnabled)
    {
        $this->securityHeadersEnabled = $securityHeadersEnabled;
        $this->securityHeadersInBackend = $securityHeadersInBackend;
        $this->xContentTypeOptions = $xContentTypeOptions;
        $this->xFrameOptions = $xFrameOptions;
        $this->referrerPolicy = $referrerPolicy;
        $this->hstsMaxAge = $hstsMaxAge;
        $this->hstsIncludeSubDomains = $hstsIncludeSubDomains;
        $this->templateGuardEnabled = $templateGuardEnabled;
        $this->templateGuardStrictIncludeMath = $templateGuardStrictIncludeMath;
        $this->uploadGuardEnabled = $uploadGuardEnabled;
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
            self::toBool($raw['templateGuardEnabled'] ?? null, true),
            self::toBool($raw['templateGuardStrictIncludeMath'] ?? null, true),
            self::toBool($raw['uploadGuardEnabled'] ?? null, true),
        );
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value, bool $default): bool
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
     * @param mixed        $value
     */
    private static function toChoice($value, array $allowed, string $default): string
    {
        if (\is_string($value) && \in_array($value, $allowed, true)) {
            return $value;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    private static function toHstsMaxAge($value): int
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
