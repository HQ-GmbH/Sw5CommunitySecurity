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

use Sw5CommunitySecurity\Components\Config\PluginConfig;

/**
 * Decides which security headers a response gets.
 *
 * Pure function of configuration, Shopware module and transport security,
 * free of Shopware dependencies so it can be unit-tested in isolation.
 */
final class HeaderPolicy
{
    public const MODULE_FRONTEND = 'frontend';
    public const MODULE_WIDGETS = 'widgets';
    public const MODULE_BACKEND = 'backend';

    /**
     * @return array<string, string> header name => header value
     */
    public static function build(PluginConfig $config, string $module, bool $isSecure): array
    {
        $module = strtolower($module);
        if (!$config->securityHeadersEnabled || !self::appliesToModule($config, $module)) {
            return [];
        }

        $headers = [];

        if ($config->xContentTypeOptions) {
            $headers['X-Content-Type-Options'] = 'nosniff';
        }

        if ($config->xFrameOptions !== PluginConfig::OFF) {
            $headers['X-Frame-Options'] = $config->xFrameOptions;
        }

        if ($config->referrerPolicy !== PluginConfig::OFF) {
            $headers['Referrer-Policy'] = $config->referrerPolicy;
        }

        // RFC 6797, section 7.2: HSTS must only be sent over secure transport.
        if ($isSecure && $config->hstsMaxAge > 0) {
            $headers['Strict-Transport-Security'] = 'max-age=' . $config->hstsMaxAge
                . ($config->hstsIncludeSubDomains ? '; includeSubDomains' : '');
        }

        $contentSecurityPolicy = self::contentSecurityPolicy($config, $module);
        if ($contentSecurityPolicy !== null) {
            $name = $config->cspMode === PluginConfig::CSP_MODE_ENFORCE
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $headers[$name] = $contentSecurityPolicy;
        }

        if ($config->permissionsPolicy !== null) {
            $headers['Permissions-Policy'] = $config->permissionsPolicy;
        }

        return $headers;
    }

    /**
     * The backend has its own policy because Ext JS needs 'unsafe-eval'.
     * A configured report URI is appended unless the policy names one itself.
     */
    private static function contentSecurityPolicy(PluginConfig $config, string $module): ?string
    {
        if ($config->cspMode === PluginConfig::OFF) {
            return null;
        }

        $policy = $module === self::MODULE_BACKEND ? $config->cspBackendPolicy : $config->cspFrontendPolicy;
        if ($policy === null) {
            return null;
        }

        if ($config->cspReportUri !== null && preg_match('/(?:^|; )report-uri(?: |$)/', $policy) !== 1) {
            $policy .= '; report-uri ' . $config->cspReportUri;
        }

        return $policy;
    }

    private static function appliesToModule(PluginConfig $config, string $module): bool
    {
        switch ($module) {
            case self::MODULE_FRONTEND:
            case self::MODULE_WIDGETS:
                return true;
            case self::MODULE_BACKEND:
                return $config->securityHeadersInBackend;
            default:
                return false;
        }
    }
}
