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

namespace Sw5CommunitySecurity\Tests\Unit\Components\UploadGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\UploadGuard\UploadInspector;

/**
 * Files used here are harmless text files; the PHAR marker is only the
 * plain text that the inspector looks for, not a working archive.
 */
final class UploadInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedNameProvider(): iterable
    {
        yield 'image' => ['produkt.jpg'];
        yield 'upper case image' => ['Produkt.PNG'];
        yield 'pdf with dots' => ['datenblatt.v1.2.pdf'];
        yield 'zip' => ['software-1.0.zip'];
        yield 'csv' => ['snippets.csv'];
        yield 'name containing php without dot' => ['phpbuch.pdf'];
        yield 'name containing phar without dot' => ['pharma-katalog.pdf'];
        yield 'language suffix pl in the middle' => ['anleitung.pl.pdf'];
    }

    #[DataProvider('allowedNameProvider')]
    public function testAllowedNames(string $name): void
    {
        self::assertFalse((new UploadInspector())->hasBlockedName($name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedNameProvider(): iterable
    {
        yield 'phar' => ['marker.phar'];
        yield 'php7' => ['marker.php7'];
        yield 'php8 upper case' => ['marker.PHP8'];
        yield 'pht' => ['marker.pht'];
        yield 'phps' => ['marker.phps'];
        yield 'double extension' => ['marker.phar.jpg'];
        yield 'php in the middle' => ['marker.php.png'];
        yield 'trailing space' => ['marker.phar '];
        yield 'perl script' => ['marker.pl'];
        yield 'shell script' => ['marker.sh'];
        yield 'htaccess' => ['.htaccess'];
        yield 'user ini' => ['.user.ini'];
        yield 'path prefix' => ['../media/marker.phar'];
        yield 'windows path prefix' => ['C:\\upload\\marker.phtml'];
    }

    #[DataProvider('blockedNameProvider')]
    public function testBlockedNames(string $name): void
    {
        self::assertTrue((new UploadInspector())->hasBlockedName($name));
    }

    public function testHarmlessFileIsAccepted(): void
    {
        self::assertNull((new UploadInspector())->inspect('bild.jpg', $this->file('harmless image placeholder')));
    }

    public function testPharMarkerIsDetected(): void
    {
        self::assertSame(
            UploadInspector::REASON_PHAR,
            (new UploadInspector())->inspect('bild.jpg', $this->file("GIF89a sw5cs marker __HALT_COMPILER(); ?>"))
        );
    }

    public function testPharMarkerIsDetectedCaseInsensitively(): void
    {
        self::assertSame(UploadInspector::REASON_PHAR, (new UploadInspector())->inspect('bild.jpg', $this->file('x __halt_compiler y')));
    }

    public function testPharMarkerAcrossChunkBoundaryIsDetected(): void
    {
        $content = str_repeat('a', 1048576 - 7) . '__HALT_COMPILER();';

        self::assertSame(UploadInspector::REASON_PHAR, (new UploadInspector())->inspect('bild.jpg', $this->file($content)));
    }

    public function testBlockedNameIsReportedWithoutReadingContent(): void
    {
        self::assertSame(UploadInspector::REASON_EXTENSION, (new UploadInspector())->inspect('marker.phar', null));
    }

    public function testUnreadableFileIsRejected(): void
    {
        self::assertSame(UploadInspector::REASON_UNREADABLE, (new UploadInspector())->inspect('bild.jpg', sys_get_temp_dir() . '/sw5cs-does-not-exist-' . bin2hex(random_bytes(4))));
    }

    private function file(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'sw5cs');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
