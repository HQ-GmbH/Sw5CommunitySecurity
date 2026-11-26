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

namespace Sw5CommunitySecurity\Tests\Unit\Metadata;

use PHPUnit\Framework\TestCase;

/**
 * Bundled third-party code (CONTRIBUTING.md, section 6.2): every library in
 * a "lib" folder ships with its license text and origin, is listed in
 * THIRD-PARTY-NOTICES.md and is part of the release ZIP.
 */
final class ThirdPartyLicensesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../';

    private const QRCODE_DIRECTORY = 'Resources/views/backend/sw5cs_two_factor/lib/qrcode-generator/';

    private const QRCODE_COPYRIGHT = 'Copyright (c) 2009 Kazuhiko Arase';

    public function testEveryBundledLibraryHasLicenseAndOrigin(): void
    {
        $libraries = glob(self::ROOT . 'Resources/views/backend/*/lib/*', GLOB_ONLYDIR);
        static::assertIsArray($libraries);
        static::assertNotSame([], $libraries);

        $notices = $this->read('THIRD-PARTY-NOTICES.md');
        foreach ($libraries as $library) {
            $relative = substr($library, \strlen(self::ROOT));
            static::assertFileExists($library . '/LICENSE', $relative);
            static::assertFileExists($library . '/ORIGIN.md', $relative);
            static::assertStringContainsString($relative . '/LICENSE', $notices, $relative . ' is missing in THIRD-PARTY-NOTICES.md');
        }
    }

    public function testQrCodeLibraryKeepsMitLicenseAndCopyright(): void
    {
        $license = $this->read(self::QRCODE_DIRECTORY . 'LICENSE');
        static::assertStringStartsWith('MIT License', $license);
        static::assertStringContainsString(self::QRCODE_COPYRIGHT, $license);
        static::assertStringContainsString('The above copyright notice and this permission notice shall be included in all', $license);

        $source = $this->read(self::QRCODE_DIRECTORY . 'qrcode.js');
        static::assertStringContainsString('// Copyright (c) 2009 Kazuhiko Arase', $source);
        static::assertStringContainsString('// Licensed under the MIT license', $source);
    }

    public function testQrCodeLibraryIsUnchanged(): void
    {
        $origin = $this->read(self::QRCODE_DIRECTORY . 'ORIGIN.md');
        static::assertSame(1, preg_match('/SHA-256 `([0-9a-f]{64})`/', $origin, $match));
        static::assertSame($match[1], hash_file('sha256', self::ROOT . self::QRCODE_DIRECTORY . 'qrcode.js'));
        static::assertStringContainsString('SPDX `MIT`', $origin);
    }

    public function testLicensesArePartOfTheReleaseZip(): void
    {
        $ignored = [];
        foreach (explode("\n", $this->read('.gitattributes')) as $line) {
            $pattern = rtrim((string) strtok($line, " \t"), '/');
            if (str_contains($line, 'export-ignore') && $pattern !== '') {
                $ignored[] = $pattern;
            }
        }

        foreach (['/LICENSE', '/THIRD-PARTY-NOTICES.md', '/LICENSES/LGPL-3.0.txt', '/Resources'] as $path) {
            foreach ($ignored as $pattern) {
                static::assertStringStartsNotWith($pattern, $path, $path . ' must not be export-ignored (' . $pattern . ')');
            }
        }
    }

    private function read(string $file): string
    {
        $content = file_get_contents(self::ROOT . $file);
        static::assertIsString($content, $file);

        return $content;
    }
}
