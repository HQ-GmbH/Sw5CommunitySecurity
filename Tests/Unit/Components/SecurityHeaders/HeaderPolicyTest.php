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

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\SecurityHeaders\HeaderPolicy;

final class HeaderPolicyTest extends TestCase
{
    public function testDefaultsForFrontend(): void
    {
        self::assertSame(
            [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ],
            HeaderPolicy::build(PluginConfig::fromArray([]), 'frontend', true)
        );
    }

    public function testWidgetsGetTheSameHeadersAsFrontend(): void
    {
        $config = PluginConfig::fromArray([]);

        self::assertSame(
            HeaderPolicy::build($config, 'frontend', false),
            HeaderPolicy::build($config, 'widgets', false)
        );
    }

    public function testDisabledModuleSendsNothing(): void
    {
        $config = PluginConfig::fromArray(['securityHeadersEnabled' => false, 'headerHstsMaxAge' => 31536000]);

        self::assertSame([], HeaderPolicy::build($config, 'frontend', true));
    }

    public function testBackendIsOptIn(): void
    {
        self::assertSame([], HeaderPolicy::build(PluginConfig::fromArray([]), 'backend', false));

        $config = PluginConfig::fromArray(['securityHeadersInBackend' => true]);
        self::assertArrayHasKey('X-Content-Type-Options', HeaderPolicy::build($config, 'backend', false));
    }

    public function testModuleNameIsCaseInsensitive(): void
    {
        self::assertNotSame([], HeaderPolicy::build(PluginConfig::fromArray([]), 'Frontend', false));
    }

    public function testUnknownModulesAreLeftAlone(): void
    {
        $config = PluginConfig::fromArray(['securityHeadersInBackend' => true]);

        self::assertSame([], HeaderPolicy::build($config, 'api', true));
        self::assertSame([], HeaderPolicy::build($config, '', true));
    }

    public function testOffValuesSuppressHeaders(): void
    {
        $config = PluginConfig::fromArray([
            'headerXContentTypeOptions' => false,
            'headerXFrameOptions' => 'off',
            'headerReferrerPolicy' => 'off',
        ]);

        self::assertSame([], HeaderPolicy::build($config, 'frontend', true));
    }

    public function testHstsIsOnlySentOverHttps(): void
    {
        $config = PluginConfig::fromArray(['headerHstsMaxAge' => 31536000]);

        self::assertArrayNotHasKey('Strict-Transport-Security', HeaderPolicy::build($config, 'frontend', false));
        self::assertSame(
            'max-age=31536000',
            HeaderPolicy::build($config, 'frontend', true)['Strict-Transport-Security']
        );
    }

    public function testHstsIncludeSubDomains(): void
    {
        $config = PluginConfig::fromArray(['headerHstsMaxAge' => '31536000', 'headerHstsIncludeSubDomains' => true]);

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            HeaderPolicy::build($config, 'frontend', true)['Strict-Transport-Security']
        );
    }
}
