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
use Sw5CommunitySecurity\Components\SecurityCheck\PhpDistributionPackage;

final class PhpDistributionPackageTest extends TestCase
{
    private const CHANGELOG = <<<'TXT'
php8.3 (8.3.6-0ubuntu0.24.04.14) noble-security; urgency=medium

  * SECURITY UPDATE: Arbitrary Code Execution
    - debian/patches/CVE-2026-115560.patch: example
    - CVE-2026-115560
    - CVE-2026-126714

 -- Example Maintainer <maintainer@example.invalid>  Thu, 19 Nov 2026 10:42:07 +1100

php8.3 (8.3.6-0ubuntu0.24.04.13) noble-security; urgency=medium

  * SECURITY UPDATE: Remote RCE via state corruption issue
    - debian/patches/CVE-2026-109618_109619.patch: example
    - CVE-2026-109618
    - CVE-2026-109619
    - CVE-2026-115560

 -- Example Maintainer <maintainer@example.invalid>  Wed, 21 Oct 2026 10:00:00 +1100
TXT;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sw5cs-php-package-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/php8.3-common', 0700, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . '/*/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        $directories = glob($this->dir . '/*');
        foreach ($directories === false ? [] : $directories as $directory) {
            rmdir($directory);
        }
        rmdir($this->dir);
    }

    public function testReadsPackageVersionAndCves(): void
    {
        $this->write('php8.3-common', self::CHANGELOG);

        static::assertSame(
            ['package' => '8.3.6-0ubuntu0.24.04.14', 'cves' => ['CVE-2026-115560', 'CVE-2026-126714', 'CVE-2026-109618', 'CVE-2026-109619']],
            (new PhpDistributionPackage($this->dir))->read('8.3.6'),
        );
    }

    public function testIgnoresPackageOfAnotherRelease(): void
    {
        $this->write('php8.3-common', self::CHANGELOG);

        static::assertNull((new PhpDistributionPackage($this->dir))->read('8.3.30'));
    }

    public function testIgnoresMissingPackage(): void
    {
        static::assertNull((new PhpDistributionPackage($this->dir))->read('8.2.29'));
        static::assertNull((new PhpDistributionPackage($this->dir . '/missing'))->read('8.3.6'));
    }

    public function testIgnoresUnexpectedContent(): void
    {
        file_put_contents($this->dir . '/php8.3-common/changelog.Debian.gz', 'not compressed');
        static::assertNull((new PhpDistributionPackage($this->dir))->read('8.3.6'));

        $this->write('php8.3-common', "something else (8.3.6-1)\n  * CVE-2026-115560\n");
        static::assertNull((new PhpDistributionPackage($this->dir))->read('8.3.6'));
    }

    public function testIgnoresUnparsableVersion(): void
    {
        static::assertNull((new PhpDistributionPackage($this->dir))->read('unknown'));
    }

    private function write(string $package, string $content): void
    {
        $compressed = gzencode($content);
        static::assertIsString($compressed);
        file_put_contents($this->dir . '/' . $package . '/changelog.Debian.gz', $compressed);
    }
}
