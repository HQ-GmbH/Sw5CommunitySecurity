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

namespace Sw5CommunitySecurity\Tests\Unit\Components\SecurityCheck;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\SecurityCheck\SmartyCacheScanner;

final class SmartyCacheScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sw5cs-cache-scan-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testFindsPluginsThatSwitchOnTheOutputCache(): void
    {
        $this->file('custom/plugins/EnablesCache/Subscriber/View.php', '<?php $view->setCaching(true);');
        $this->file('custom/plugins/EnablesWithoutArgument/Bootstrap.php', '<?php $args->getSubject()->View()->setCaching();');
        $this->file('custom/plugins/AssignsLifetime/Bootstrap.php', '<?php $this->template->caching = \Smarty::CACHING_LIFETIME_CURRENT;');
        $this->file('engine/Shopware/Plugins/Community/Frontend/LegacyCache/Bootstrap.php', '<?php $view->setCaching(1);');
        $this->file('custom/plugins/SwitchesOff/Bootstrap.php', '<?php $view->setCaching(false); $smarty->caching = 0; $smarty->caching = Smarty::CACHING_OFF; if ($smarty->caching == 1) {}');
        $this->file('custom/plugins/OnlyVendor/vendor/smarty/smarty/libs/Smarty.class.php', '<?php $this->setCaching(true);');
        $this->file('custom/plugins/OnlyTests/Tests/CacheTest.php', '<?php $view->setCaching(true);');
        $this->file('custom/plugins/Sw5CommunitySecurity/Docs.php', '<?php $view->setCaching(true);');
        $this->file('custom/plugins/NotPhp/notes.txt', '$view->setCaching(true);');

        static::assertSame([
            'custom/plugins/AssignsLifetime',
            'custom/plugins/EnablesCache',
            'custom/plugins/EnablesWithoutArgument',
            'engine/Shopware/Plugins/Community/Frontend/LegacyCache',
        ], (new SmartyCacheScanner())->pluginsEnablingCache($this->root));
    }

    public function testShopWithoutPluginDirectoriesHasNoFindings(): void
    {
        mkdir($this->root, 0777, true);

        static::assertSame([], (new SmartyCacheScanner())->pluginsEnablingCache($this->root));
    }

    private function file(string $path, string $content): void
    {
        $full = $this->root . '/' . $path;
        if (!is_dir(\dirname($full))) {
            mkdir(\dirname($full), 0777, true);
        }
        file_put_contents($full, $content);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        foreach (\is_array($entries) ? $entries : [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
