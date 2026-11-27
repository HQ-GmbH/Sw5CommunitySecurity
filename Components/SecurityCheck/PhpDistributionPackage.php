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

namespace Sw5CommunitySecurity\Components\SecurityCheck;

/**
 * Reads the package changelog of a Debian or Ubuntu PHP package.
 *
 * These distributions keep the upstream version number (e.g. 8.3.6) and
 * backport security fixes; each fix is listed with its CVE id in
 * /usr/share/doc/php<branch>-common/changelog.Debian.gz. Read-only, fixed
 * paths only; returns null when the file is missing, unreadable (e.g.
 * open_basedir) or does not belong to the running PHP release.
 */
final class PhpDistributionPackage
{
    public const DEFAULT_DOC_DIR = '/usr/share/doc';

    private const MAX_COMPRESSED_BYTES = 2097152;

    private const MAX_DECOMPRESSED_BYTES = 16777216;

    private string $docDir;

    public function __construct(string $docDir = self::DEFAULT_DOC_DIR)
    {
        $this->docDir = rtrim($docDir, '/');
    }

    /**
     * @return array{package: string, cves: list<string>}|null
     */
    public function read(string $phpVersion): ?array
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $phpVersion, $match) !== 1) {
            return null;
        }

        $file = $this->docDir . '/php' . $match[1] . '.' . $match[2] . '-common/changelog.Debian.gz';
        $changelog = $this->decompress($file);
        if ($changelog === null) {
            return null;
        }

        // First line: "php8.3 (8.3.6-0ubuntu0.24.04.14) noble-security; urgency=medium"
        if (preg_match('/\A\s*php\d+\.\d+ \(((\d+\.\d+\.\d+)[^)\s]*)\)/', $changelog, $head) !== 1 || $head[2] !== $match[0]) {
            return null;
        }

        preg_match_all('/\bCVE-\d{4}-\d{4,}\b/', $changelog, $cves);

        return ['package' => $head[1], 'cves' => array_values(array_unique($cves[0]))];
    }

    private function decompress(string $file): ?string
    {
        if (!\function_exists('gzdecode')) {
            return null;
        }

        set_error_handler(static fn (): bool => true);
        try {
            if (!is_file($file) || !is_readable($file)) {
                return null;
            }
            $size = filesize($file);
            if ($size === false || $size > self::MAX_COMPRESSED_BYTES) {
                return null;
            }
            $compressed = file_get_contents($file);
            if ($compressed === false) {
                return null;
            }
            $content = gzdecode($compressed, self::MAX_DECOMPRESSED_BYTES);

            return \is_string($content) ? $content : null;
        } finally {
            restore_error_handler();
        }
    }
}
