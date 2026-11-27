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

namespace Sw5CommunitySecurity\Components\UploadGuard;

/**
 * Detects uploads that a web server may execute or PHP may treat as a PHAR
 * archive.
 *
 * Shopware 5.7 blocks "php", "php3"–"php5" and "phtml" for media and ESD
 * uploads, but not "phar", "php7", "php8", "pht" and others that common
 * web server configurations hand to PHP; media files are publicly reachable.
 * Every dot-separated part of the file name is checked, because some server
 * configurations execute "file.phar.jpg". The content is checked for the
 * PHAR stub marker "__HALT_COMPILER", which every PHAR archive contains,
 * also when disguised as an image.
 *
 * Free of Shopware dependencies to keep it unit-testable.
 */
final class UploadInspector
{
    public const REASON_EXTENSION = 'extension';
    public const REASON_PHAR = 'phar';
    public const REASON_UNREADABLE = 'unreadable';

    /**
     * Lower-case file name parts that PHP handlers commonly match; they must
     * not occur anywhere after the first dot ("file.phar.jpg").
     */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phtm', 'phps', 'phpt', 'pgif',
        'phar', 'shtml',
    ];

    /**
     * Further lower-case extensions that are blocked as the last extension
     * only, so that names such as "manual.pl.pdf" remain possible.
     */
    public const BLOCKED_FINAL_EXTENSIONS = ['htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp'];

    /**
     * Lower-case file names that configure the web server or PHP.
     */
    public const BLOCKED_FILE_NAMES = ['.htaccess', '.htpasswd', '.user.ini', 'user.ini', 'php.ini', 'web.config'];

    private const PHAR_MARKER = '__halt_compiler';
    private const CHUNK_SIZE = 1048576;

    /**
     * @return self::REASON_*|null null if the upload is acceptable
     */
    public function inspect(string $originalName, ?string $path): ?string
    {
        if ($this->hasBlockedName($originalName)) {
            return self::REASON_EXTENSION;
        }

        if ($path === null) {
            return null;
        }

        return $this->containsPharMarker($path);
    }

    public function hasBlockedName(string $originalName): bool
    {
        $name = strtolower(basename(str_replace('\\', '/', trim($originalName))));
        if (\in_array($name, self::BLOCKED_FILE_NAMES, true)) {
            return true;
        }

        // The first part is the base name; it is empty for ".phar" or "..phar",
        // whose extension is still "phar" (pathinfo() and web servers agree).
        $parts = explode('.', $name);
        array_shift($parts);

        foreach ($parts as $part) {
            if (\in_array(trim($part), self::BLOCKED_EXTENSIONS, true)) {
                return true;
            }
        }

        $last = trim((string) end($parts));

        return $parts !== [] && \in_array($last, self::BLOCKED_FINAL_EXTENSIONS, true);
    }

    public function containsPharMarkerInString(string $content): bool
    {
        return stripos($content, self::PHAR_MARKER) !== false;
    }

    /**
     * @return self::REASON_PHAR|self::REASON_UNREADABLE|null
     */
    private function containsPharMarker(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return self::REASON_UNREADABLE;
        }

        $overlap = \strlen(self::PHAR_MARKER) - 1;
        $tail = '';

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    return self::REASON_UNREADABLE;
                }

                $buffer = $tail . $chunk;
                if ($this->containsPharMarkerInString($buffer)) {
                    return self::REASON_PHAR;
                }

                $tail = substr($buffer, -$overlap);
            }
        } finally {
            fclose($handle);
        }

        return null;
    }
}
