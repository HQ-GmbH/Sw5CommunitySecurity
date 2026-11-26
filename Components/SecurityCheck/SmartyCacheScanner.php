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

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Finds plugins that switch on Smarty's output cache in their PHP code.
 *
 * CVE-2026-82531 (Smarty < 4.5.8, GHSA-3w63-v7pm-cq9x) needs the output cache.
 * Shopware itself never enables it (Smarty::$caching defaults to false,
 * engine/Library/Smarty/Smarty.class.php:282), but plugins can, e.g. via
 * Enlight_View_Default::setCaching(true). This read-only scan looks for such
 * calls in the plugin directories; vendor, test and node_modules folders are
 * skipped. A hit is a hint to check the plugin, not proof of a vulnerability.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class SmartyCacheScanner
{
    /**
     * Plugin directories relative to the shop root (Shopware 5 plugin system
     * and legacy plugins).
     */
    public const PLUGIN_DIRECTORIES = [
        'custom/plugins',
        'custom/project',
        'engine/Shopware/Plugins/Community',
        'engine/Shopware/Plugins/Local',
    ];

    public const MAX_FILES = 20000;

    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    private const SKIPPED_DIRECTORIES = ['vendor', 'node_modules', 'tests', 'test', '.git'];

    /**
     * setCaching() without argument or with anything but false/0, or an
     * assignment to ->caching other than false/0/null/Smarty::CACHING_OFF.
     */
    private const PATTERN = '/->\s*setCaching\s*\(\s*+(?!false\s*\)|0\s*\))|->\s*caching\s*=(?!=)\s*+(?!false\b|0\b|null\b|\\\\?Smarty::CACHING_OFF\b)/i';

    /**
     * @param string $ownPlugin directory name of this plugin, which is skipped
     *
     * @return list<string> plugin directory names (e.g. "custom/plugins/Example"), sorted
     */
    public function pluginsEnablingCache(string $shopRoot, string $ownPlugin = 'Sw5CommunitySecurity'): array
    {
        $found = [];
        $files = 0;

        foreach (self::PLUGIN_DIRECTORIES as $base) {
            $baseDirectory = rtrim($shopRoot, '/') . '/' . $base;
            if (!is_dir($baseDirectory)) {
                continue;
            }

            foreach ($this->phpFiles($baseDirectory) as $file) {
                if (++$files > self::MAX_FILES) {
                    break 2;
                }

                $plugin = $this->pluginOf($baseDirectory, $file->getPathname(), $base);
                if ($plugin === null || isset($found[$plugin]) || basename($plugin) === $ownPlugin) {
                    continue;
                }

                if ($file->getSize() > self::MAX_FILE_SIZE) {
                    continue;
                }
                $code = @file_get_contents($file->getPathname());
                if (\is_string($code) && preg_match(self::PATTERN, $code) === 1) {
                    $found[$plugin] = true;
                }
            }
        }

        $plugins = array_keys($found);
        sort($plugins);

        return $plugins;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $directory): iterable
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                    static fn (SplFileInfo $file): bool => !$file->isDir() || !\in_array(strtolower($file->getFilename()), self::SKIPPED_DIRECTORIES, true),
                ),
            );
        } catch (Throwable $exception) {
            return [];
        }

        $files = [];
        try {
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file;
                }
            }
        } catch (Throwable $exception) {
            // Unreadable directory: check what was found so far.
        }

        return $files;
    }

    /**
     * Plugin = first directory below the plugin base (e.g. custom/plugins/Example);
     * legacy plugins sit one level deeper (Community/Frontend/Example).
     */
    private function pluginOf(string $baseDirectory, string $path, string $base): ?string
    {
        $parts = explode('/', (string) substr($path, \strlen($baseDirectory) + 1));
        $depth = strpos($base, 'engine/Shopware/Plugins/') === 0 ? 2 : 1;
        if (\count($parts) <= $depth) {
            return null;
        }

        return $base . '/' . implode('/', \array_slice($parts, 0, $depth));
    }
}
