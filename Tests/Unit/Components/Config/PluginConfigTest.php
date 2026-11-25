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

namespace Sw5CommunitySecurity\Tests\Unit\Components\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Config\PluginConfig;

final class PluginConfigTest extends TestCase
{
    public function testEmptyConfigurationFallsBackToDefaults(): void
    {
        $config = PluginConfig::fromArray([]);

        static::assertTrue($config->securityHeadersEnabled);
        static::assertFalse($config->securityHeadersInBackend);
        static::assertTrue($config->xContentTypeOptions);
        static::assertSame('SAMEORIGIN', $config->xFrameOptions);
        static::assertSame('strict-origin-when-cross-origin', $config->referrerPolicy);
        static::assertSame(0, $config->hstsMaxAge);
        static::assertFalse($config->hstsIncludeSubDomains);
        static::assertTrue($config->templateGuardEnabled);
        static::assertTrue($config->templateGuardStrictIncludeMath);
        static::assertTrue($config->uploadGuardEnabled);
        static::assertTrue($config->javaScriptEscapeEnabled);
        static::assertTrue($config->protectionFilesAutoRestore);
        static::assertFalse($config->customerSessionSingleLogin);
        static::assertSame(PluginConfig::OFF, $config->customerSessionCookieSameSite);
        static::assertTrue($config->backendTwoFactorEnabled);
        static::assertFalse($config->backendPasswordPolicyEnabled);
        static::assertSame(12, $config->backendPasswordMinLength);
        static::assertSame(PluginConfig::OFF, $config->cspMode);
        static::assertSame(PluginConfig::DEFAULT_CSP_FRONTEND, $config->cspFrontendPolicy);
        static::assertSame(PluginConfig::DEFAULT_CSP_BACKEND, $config->cspBackendPolicy);
        static::assertNull($config->cspReportUri);
        static::assertSame(PluginConfig::DEFAULT_PERMISSIONS_POLICY, $config->permissionsPolicy);
    }

    public function testCspModeIsWhitelisted(): void
    {
        static::assertSame('report-only', PluginConfig::fromArray(['headerCspMode' => 'report-only'])->cspMode);
        static::assertSame('enforce', PluginConfig::fromArray(['headerCspMode' => 'enforce'])->cspMode);
        static::assertSame(PluginConfig::OFF, PluginConfig::fromArray(['headerCspMode' => 'block-all'])->cspMode);
        static::assertSame(PluginConfig::OFF, PluginConfig::fromArray(['headerCspMode' => 1])->cspMode);
    }

    public function testEmptyHeaderValuesDisableTheHeader(): void
    {
        $config = PluginConfig::fromArray(['headerPermissionsPolicy' => '', 'headerCspPolicy' => '   ', 'headerCspReportUri' => '']);

        static::assertNull($config->permissionsPolicy);
        static::assertNull($config->cspFrontendPolicy);
        static::assertNull($config->cspReportUri);
    }

    public function testNonStringHeaderValuesAreRejected(): void
    {
        static::assertNull(PluginConfig::fromArray(['headerPermissionsPolicy' => ['camera=()']])->permissionsPolicy);
    }

    public function testTemplateGuardCanBeDisabled(): void
    {
        static::assertFalse(PluginConfig::fromArray(['templateGuardEnabled' => false])->templateGuardEnabled);
        static::assertFalse(PluginConfig::fromArray(['templateGuardStrictIncludeMath' => '0'])->templateGuardStrictIncludeMath);
        static::assertFalse(PluginConfig::fromArray(['uploadGuardEnabled' => false])->uploadGuardEnabled);
        static::assertFalse(PluginConfig::fromArray(['javaScriptEscapeEnabled' => '0'])->javaScriptEscapeEnabled);
        static::assertFalse(PluginConfig::fromArray(['protectionFilesAutoRestore' => false])->protectionFilesAutoRestore);
    }

    public function testPasswordMinLengthIsClamped(): void
    {
        static::assertSame(16, PluginConfig::fromArray(['backendPasswordMinLength' => '16'])->backendPasswordMinLength);
        static::assertSame(20, PluginConfig::fromArray(['backendPasswordMinLength' => 20.0])->backendPasswordMinLength);
        static::assertSame(8, PluginConfig::fromArray(['backendPasswordMinLength' => 3])->backendPasswordMinLength);
        static::assertSame(64, PluginConfig::fromArray(['backendPasswordMinLength' => 500])->backendPasswordMinLength);
        static::assertSame(12, PluginConfig::fromArray(['backendPasswordMinLength' => 'abc'])->backendPasswordMinLength);
    }

    public function testCustomerSessionOptions(): void
    {
        static::assertTrue(PluginConfig::fromArray(['customerSessionSingleLogin' => '1'])->customerSessionSingleLogin);
        static::assertSame(PluginConfig::SAME_SITE_LAX, PluginConfig::fromArray(['customerSessionCookieSameSite' => 'lax'])->customerSessionCookieSameSite);
        static::assertSame(PluginConfig::SAME_SITE_STRICT, PluginConfig::fromArray(['customerSessionCookieSameSite' => 'strict'])->customerSessionCookieSameSite);
        static::assertSame(PluginConfig::OFF, PluginConfig::fromArray(['customerSessionCookieSameSite' => 'None'])->customerSessionCookieSameSite);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function booleanProvider(): iterable
    {
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string "1"' => ['1', true];
        yield 'string "0"' => ['0', false];
        yield 'string "false"' => ['false', false];
        yield 'garbage keeps default' => ['maybe', true];
        yield 'array keeps default' => [['x'], true];
    }

    #[DataProvider('booleanProvider')]
    public function testBooleansAreNormalised(mixed $raw, bool $expected): void
    {
        $config = PluginConfig::fromArray(['headerXContentTypeOptions' => $raw]);

        static::assertSame($expected, $config->xContentTypeOptions);
    }

    public function testAllowedChoicesArePassedThrough(): void
    {
        $config = PluginConfig::fromArray([
            'headerXFrameOptions' => 'DENY',
            'headerReferrerPolicy' => 'no-referrer',
        ]);

        static::assertSame('DENY', $config->xFrameOptions);
        static::assertSame('no-referrer', $config->referrerPolicy);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidChoiceProvider(): iterable
    {
        yield 'header injection attempt' => ["DENY\r\nSet-Cookie: x=1"];
        yield 'unsupported value' => ['ALLOW-FROM https://example.org'];
        yield 'wrong case' => ['deny'];
        yield 'not a string' => [42];
    }

    #[DataProvider('invalidChoiceProvider')]
    public function testInvalidChoicesFallBackToDefaults(mixed $raw): void
    {
        $config = PluginConfig::fromArray([
            'headerXFrameOptions' => $raw,
            'headerReferrerPolicy' => $raw,
        ]);

        static::assertSame('SAMEORIGIN', $config->xFrameOptions);
        static::assertSame('strict-origin-when-cross-origin', $config->referrerPolicy);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function hstsMaxAgeProvider(): iterable
    {
        yield 'int' => [31536000, 31536000];
        yield 'numeric string' => ['31536000', 31536000];
        yield 'integral float' => [31536000.0, 31536000];
        yield 'zero disables' => [0, 0];
        yield 'negative disables' => [-1, 0];
        yield 'fraction disables' => [1.5, 0];
        yield 'non-numeric string disables' => ['1 year', 0];
        yield 'capped at two years' => [999999999, PluginConfig::HSTS_MAX_AGE_LIMIT];
    }

    #[DataProvider('hstsMaxAgeProvider')]
    public function testHstsMaxAgeIsValidated(mixed $raw, int $expected): void
    {
        static::assertSame($expected, PluginConfig::fromArray(['headerHstsMaxAge' => $raw])->hstsMaxAge);
    }
}
