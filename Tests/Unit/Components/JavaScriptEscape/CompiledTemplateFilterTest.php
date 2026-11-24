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

namespace Sw5CommunitySecurity\Tests\Unit\Components\JavaScriptEscape;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\JavaScriptEscape\CompiledTemplateFilter;
use Sw5CommunitySecurity\Components\JavaScriptEscape\JavaScriptEscapeHardening;

final class CompiledTemplateFilterTest extends TestCase
{
    private const COMPILED = '<?php echo strtr($_smarty_tpl->tpl_vars[\'value\']->value, '
        . JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE . ');?>';

    public function testHardensWhenEnabled(): void
    {
        $filter = new CompiledTemplateFilter(self::source(['javaScriptEscapeEnabled' => true]), new JavaScriptEscapeHardening());

        static::assertStringNotContainsString(JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE, $filter->filter(self::COMPILED));
    }

    public function testLeavesCodeUnchangedWhenDisabled(): void
    {
        $filter = new CompiledTemplateFilter(self::source(['javaScriptEscapeEnabled' => false]), new JavaScriptEscapeHardening());

        static::assertSame(self::COMPILED, $filter->filter(self::COMPILED));
    }

    public function testStaysActiveWhenTheConfigurationCannotBeRead(): void
    {
        $failing = new class implements DefaultShopConfigSource {
            public function getForDefaultShop(): PluginConfig
            {
                throw new RuntimeException('configuration unavailable');
            }
        };
        $filter = new CompiledTemplateFilter($failing, new JavaScriptEscapeHardening());

        static::assertStringNotContainsString(JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE, $filter->filter(self::COMPILED));
    }

    public function testDoesNotReadTheConfigurationForOtherTemplates(): void
    {
        $failing = new class implements DefaultShopConfigSource {
            public function getForDefaultShop(): PluginConfig
            {
                throw new RuntimeException('must not be called');
            }
        };
        $filter = new CompiledTemplateFilter($failing, new JavaScriptEscapeHardening());
        $code = '<?php echo $_smarty_tpl->tpl_vars[\'value\']->value;?>';

        static::assertSame($code, $filter->filter($code, null));
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function source(array $raw): DefaultShopConfigSource
    {
        return new class($raw) implements DefaultShopConfigSource {
            /**
             * @var array<string, mixed>
             */
            private array $raw;

            /**
             * @param array<string, mixed> $raw
             */
            public function __construct(array $raw)
            {
                $this->raw = $raw;
            }

            public function getForDefaultShop(): PluginConfig
            {
                return PluginConfig::fromArray($this->raw);
            }
        };
    }
}
