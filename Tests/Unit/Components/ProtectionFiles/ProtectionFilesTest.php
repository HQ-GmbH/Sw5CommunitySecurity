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
use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;

final class ProtectionFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sw5cs-protection-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testNothingIsMissingOrRestoredWithoutDirectories(): void
    {
        $files = new ProtectionFiles();

        static::assertSame([], $files->missing($this->root));
        $result = $files->restore($this->root);
        static::assertSame([], $result->restored);
        static::assertSame([], $result->failed);
        static::assertFalse($result->hasChanges());
    }

    public function testMissingFilesInExistingDirectoriesAreRestored(): void
    {
        $this->makeDirectories(['var/log', 'vendor', 'engine/Library']);
        $files = new ProtectionFiles();

        static::assertSame(['engine/Library/.htaccess', 'var/log/.htaccess', 'vendor/.htaccess'], $files->missing($this->root));

        $result = $files->restore($this->root);

        static::assertSame(['engine/Library/.htaccess', 'var/log/.htaccess', 'vendor/.htaccess'], $result->restored);
        static::assertSame([], $result->failed);
        static::assertSame([], $files->missing($this->root));
        static::assertSame(ProtectionFiles::content(ProtectionFiles::RULE_DENY_ALL), file_get_contents($this->root . '/vendor/.htaccess'));
        static::assertSame(ProtectionFiles::content(ProtectionFiles::RULE_DENY_PHP), file_get_contents($this->root . '/engine/Library/.htaccess'));
        static::assertFalse($files->restore($this->root)->hasChanges());
    }

    public function testExistingFilesAreNeverChanged(): void
    {
        $this->makeDirectories(['var/log']);
        file_put_contents($this->root . '/var/log/.htaccess', "# custom\n");

        $result = (new ProtectionFiles())->restore($this->root);

        static::assertSame([], $result->restored);
        static::assertSame("# custom\n", file_get_contents($this->root . '/var/log/.htaccess'));
    }

    public function testSymbolicLinkToExistingFileCountsAsPresent(): void
    {
        $this->makeDirectories(['var/log']);
        file_put_contents($this->root . '/shared.htaccess', "# shared\n");
        symlink($this->root . '/shared.htaccess', $this->root . '/var/log/.htaccess');
        $files = new ProtectionFiles();

        static::assertSame([], $files->missing($this->root));
        static::assertFalse($files->restore($this->root)->hasChanges());
        static::assertSame("# shared\n", file_get_contents($this->root . '/shared.htaccess'));
    }

    public function testDanglingSymbolicLinkIsNotFollowed(): void
    {
        $this->makeDirectories(['var/log']);
        $target = $this->root . '/elsewhere.txt';
        symlink($target, $this->root . '/var/log/.htaccess');

        $files = new ProtectionFiles();
        static::assertSame(['var/log/.htaccess'], $files->missing($this->root));

        $result = $files->restore($this->root);

        static::assertSame(['var/log/.htaccess'], $result->failed);
        static::assertFileDoesNotExist($target);
    }

    public function testUnwritableDirectoryIsReportedAsFailed(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            static::markTestSkipped('root ignores directory permissions');
        }

        $this->makeDirectories(['var/cache']);
        chmod($this->root . '/var/cache', 0555);

        try {
            $result = (new ProtectionFiles())->restore($this->root);
        } finally {
            chmod($this->root . '/var/cache', 0755);
        }

        static::assertSame(['var/cache/.htaccess'], $result->failed);
        static::assertSame([], $result->restored);
    }

    public function testContentDeniesAccessForApache22And24(): void
    {
        foreach ([ProtectionFiles::RULE_DENY_ALL, ProtectionFiles::RULE_DENY_PHP] as $rule) {
            $content = ProtectionFiles::content($rule);
            static::assertStringContainsString('Require all denied', $content);
            static::assertStringContainsString('Deny from all', $content);
            static::assertStringContainsString('<IfModule mod_authz_core.c>', $content);
            static::assertStringContainsString('<IfModule !mod_authz_core.c>', $content);
        }

        static::assertStringContainsString('<FilesMatch "\.php$">', ProtectionFiles::content(ProtectionFiles::RULE_DENY_PHP));
        static::assertStringNotContainsString('FilesMatch', ProtectionFiles::content(ProtectionFiles::RULE_DENY_ALL));
    }

    /**
     * @param list<string> $directories
     */
    private function makeDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            mkdir($this->root . '/' . $directory, 0755, true);
        }
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }
}
