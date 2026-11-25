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

namespace Sw5CommunitySecurity\Tests\Unit\Components\ProtectionFiles;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\ProtectionFiles\AutoRestore;
use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;
use Sw5CommunitySecurity\Tests\Unit\Support\RecordingLogger;

final class AutoRestoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sw5cs-subscriber-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var/log', 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/var/log/.htaccess');
        rmdir($this->root . '/var/log');
        rmdir($this->root . '/var');
        rmdir($this->root);
    }

    public function testRestoresAndLogsOnlyPaths(): void
    {
        $logger = new RecordingLogger();
        (new AutoRestore(self::config(true), new ProtectionFiles(), $this->root, $logger))->run();

        static::assertFileExists($this->root . '/var/log/.htaccess');
        static::assertSame([['warning', 'Missing protection files were restored.', ['files' => ['var/log/.htaccess']]]], $logger->records);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $logger = new RecordingLogger();
        (new AutoRestore(self::config(false), new ProtectionFiles(), $this->root, $logger))->run();

        static::assertFileDoesNotExist($this->root . '/var/log/.htaccess');
        static::assertSame([], $logger->records);
    }

    public function testNeverThrows(): void
    {
        $failing = new class implements DefaultShopConfigSource {
            public function getForDefaultShop(): PluginConfig
            {
                throw new RuntimeException('database unavailable');
            }
        };
        $logger = new RecordingLogger();

        (new AutoRestore($failing, new ProtectionFiles(), $this->root, $logger))->run();

        static::assertSame([['error', 'Checking protection files failed.', ['exception' => RuntimeException::class]]], $logger->records);
    }

    private static function config(bool $autoRestore): DefaultShopConfigSource
    {
        return new class($autoRestore) implements DefaultShopConfigSource {
            private bool $autoRestore;

            public function __construct(bool $autoRestore)
            {
                $this->autoRestore = $autoRestore;
            }

            public function getForDefaultShop(): PluginConfig
            {
                return PluginConfig::fromArray(['protectionFilesAutoRestore' => $this->autoRestore]);
            }
        };
    }
}
