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

namespace Sw5CommunitySecurity\Tests\Unit\Components\SecurityHeaders;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\SecurityHeaders\HeaderValueNormalizer;

final class HeaderValueNormalizerTest extends TestCase
{
    public function testDefaultPoliciesAreValidAndUnchanged(): void
    {
        static::assertSame(PluginConfig::DEFAULT_CSP_FRONTEND, HeaderValueNormalizer::contentSecurityPolicy(PluginConfig::DEFAULT_CSP_FRONTEND));
        static::assertSame(PluginConfig::DEFAULT_CSP_BACKEND, HeaderValueNormalizer::contentSecurityPolicy(PluginConfig::DEFAULT_CSP_BACKEND));
        static::assertSame(PluginConfig::DEFAULT_PERMISSIONS_POLICY, HeaderValueNormalizer::permissionsPolicy(PluginConfig::DEFAULT_PERMISSIONS_POLICY));
    }

    public function testCspIsNormalised(): void
    {
        static::assertSame(
            "default-src 'self'; img-src 'self' https://cdn.example.org; upgrade-insecure-requests",
            HeaderValueNormalizer::contentSecurityPolicy("  Default-Src   'self' ;; img-src 'self'  https://cdn.example.org; upgrade-insecure-requests ;  "),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCspProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'only separators' => [' ; ; '];
        yield 'line break' => ["default-src 'self'\r\nX-Injected: 1"];
        yield 'tab' => ["default-src\t'self'"];
        yield 'comma starts a second policy' => ["default-src 'self', script-src *"];
        yield 'non-ASCII' => ["default-src 'self' https://exämple.org"];
        yield 'invalid directive name' => ["default_src 'self'"];
        yield 'directive named twice' => ["script-src 'self'; script-src *"];
        yield 'too long' => ['default-src ' . str_repeat('a', HeaderValueNormalizer::MAX_LENGTH)];
    }

    #[DataProvider('invalidCspProvider')]
    public function testInvalidCspIsRejected(string $value): void
    {
        static::assertNull(HeaderValueNormalizer::contentSecurityPolicy($value));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function reportUriProvider(): iterable
    {
        yield 'https URL' => ['https://csp.example.org/report', 'https://csp.example.org/report'];
        yield 'surrounding spaces' => ['  https://csp.example.org/r?id=1  ', 'https://csp.example.org/r?id=1'];
        yield 'empty' => ['', null];
        yield 'http is rejected' => ['http://csp.example.org/report', null];
        yield 'relative path is rejected' => ['/csp-report', null];
        yield 'semicolon would add a directive' => ['https://csp.example.org/r;script-src *', null];
        yield 'comma would add a policy' => ['https://csp.example.org/r,x', null];
        yield 'space' => ['https://csp.example.org/a b', null];
        yield 'line break' => ["https://csp.example.org/\r\nX: 1", null];
    }

    #[DataProvider('reportUriProvider')]
    public function testReportUri(string $value, ?string $expected): void
    {
        static::assertSame($expected, HeaderValueNormalizer::reportUri($value));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function permissionsPolicyProvider(): iterable
    {
        yield 'empty allowlists' => ['camera=(), microphone=()', 'camera=(), microphone=()'];
        yield 'spacing is normalised' => ['camera = ( ) ,  geolocation=( self   "https://maps.example.org" )', 'camera=(), geolocation=(self "https://maps.example.org")'];
        yield 'wildcard and self' => ['fullscreen=*, autoplay=self', 'fullscreen=*, autoplay=self'];
        yield 'empty' => ['', null];
        yield 'missing allowlist' => ['camera', null];
        yield 'unquoted origin' => ['camera=(https://example.org)', null];
        yield 'feature named twice' => ['camera=(), camera=*', null];
        yield 'semicolon' => ['camera=(); microphone=()', null];
        yield 'line break' => ["camera=()\r\nX: 1", null];
    }

    #[DataProvider('permissionsPolicyProvider')]
    public function testPermissionsPolicy(string $value, ?string $expected): void
    {
        static::assertSame($expected, HeaderValueNormalizer::permissionsPolicy($value));
    }
}
