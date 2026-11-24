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

use Sw5CommunitySecurity\Components\SecurityHeaders\HeaderValueNormalizer;

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

    public const CSP_MODE_REPORT_ONLY = 'report-only';
    public const CSP_MODE_ENFORCE = 'enforce';
    public const CSP_MODE_VALUES = [self::OFF, self::CSP_MODE_REPORT_ONLY, self::CSP_MODE_ENFORCE];

    /**
     * Starting point for the Shopware 5 Responsive theme: inline scripts and
     * styles are part of the theme, so they stay allowed; plugins (payment,
     * tracking, consent) usually need further sources. Keep in sync with
     * Resources/config.xml.
     */
    public const DEFAULT_CSP_FRONTEND = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; frame-src 'self' https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

    /**
     * The Ext JS backend also evaluates code at runtime ('unsafe-eval').
     * Keep in sync with Resources/config.xml.
     */
    public const DEFAULT_CSP_BACKEND = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

    /**
     * Features a shop does not need; payment and geolocation stay untouched
     * because payment and store locator plugins may use them.
     * Keep in sync with Resources/config.xml.
     */
    public const DEFAULT_PERMISSIONS_POLICY = 'camera=(), microphone=(), usb=()';

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

    /**
     * @readonly
     */
    public bool $javaScriptEscapeEnabled;

    /**
     * @readonly
     *
     * @var self::OFF|self::CSP_MODE_REPORT_ONLY|self::CSP_MODE_ENFORCE
     */
    public string $cspMode;

    /**
     * Null if the configured policy is invalid; no header is sent then.
     *
     * @readonly
     */
    public ?string $cspFrontendPolicy;

    /**
     * @readonly
     */
    public ?string $cspBackendPolicy;

    /**
     * @readonly
     */
    public ?string $cspReportUri;

    /**
     * Null if disabled (empty) or invalid.
     *
     * @readonly
     */
    public ?string $permissionsPolicy;

    /**
     * @param self::OFF|self::CSP_MODE_REPORT_ONLY|self::CSP_MODE_ENFORCE $cspMode
     */
    private function __construct(bool $securityHeadersEnabled, bool $securityHeadersInBackend, bool $xContentTypeOptions, string $xFrameOptions, string $referrerPolicy, int $hstsMaxAge, bool $hstsIncludeSubDomains, bool $templateGuardEnabled, bool $templateGuardStrictIncludeMath, bool $uploadGuardEnabled, bool $javaScriptEscapeEnabled, string $cspMode, ?string $cspFrontendPolicy, ?string $cspBackendPolicy, ?string $cspReportUri, ?string $permissionsPolicy)
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
        $this->javaScriptEscapeEnabled = $javaScriptEscapeEnabled;
        $this->cspMode = $cspMode;
        $this->cspFrontendPolicy = $cspFrontendPolicy;
        $this->cspBackendPolicy = $cspBackendPolicy;
        $this->cspReportUri = $cspReportUri;
        $this->permissionsPolicy = $permissionsPolicy;
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
            self::toBool($raw['javaScriptEscapeEnabled'] ?? null, true),
            self::toCspMode($raw['headerCspMode'] ?? null),
            self::toHeaderValue($raw['headerCspPolicy'] ?? null, self::DEFAULT_CSP_FRONTEND, [HeaderValueNormalizer::class, 'contentSecurityPolicy']),
            self::toHeaderValue($raw['headerCspBackendPolicy'] ?? null, self::DEFAULT_CSP_BACKEND, [HeaderValueNormalizer::class, 'contentSecurityPolicy']),
            self::toHeaderValue($raw['headerCspReportUri'] ?? null, '', [HeaderValueNormalizer::class, 'reportUri']),
            self::toHeaderValue($raw['headerPermissionsPolicy'] ?? null, self::DEFAULT_PERMISSIONS_POLICY, [HeaderValueNormalizer::class, 'permissionsPolicy']),
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
     *
     * @return self::OFF|self::CSP_MODE_REPORT_ONLY|self::CSP_MODE_ENFORCE
     */
    private static function toCspMode($value): string
    {
        if ($value === self::CSP_MODE_REPORT_ONLY || $value === self::CSP_MODE_ENFORCE) {
            return $value;
        }

        return self::OFF;
    }

    /**
     * A missing value falls back to the default; an empty or invalid value
     * yields null (header not sent).
     *
     * @param mixed                     $value
     * @param callable(string): ?string $normalize
     */
    private static function toHeaderValue($value, string $default, callable $normalize): ?string
    {
        if ($value === null) {
            $value = $default;
        }

        if (!\is_string($value)) {
            return null;
        }

        return $normalize($value);
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
