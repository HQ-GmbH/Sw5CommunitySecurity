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
        return $this->scan($shopRoot, $ownPlugin)['plugins'];
    }

    /**
     * Like pluginsEnablingCache(), and whether every plugin file could be
     * checked. The scan is incomplete if a directory or file could not be
     * read or the file limit (MAX_FILES) was reached; plugins found up to
     * then are still returned.
     *
     * Plugin directories that are symbolic links (common for deployments and
     * Composer path repositories) are followed; below a plugin directory,
     * links are not followed, and every target is scanned once, so links
     * cannot cause loops.
     *
     * @param string $ownPlugin directory name of this plugin, which is skipped
     *
     * @return array{plugins: list<string>, complete: bool}
     */
    public function scan(string $shopRoot, string $ownPlugin = 'Sw5CommunitySecurity'): array
    {
        $found = [];
        $files = 0;
        $complete = true;
        $visited = [];

        foreach (self::PLUGIN_DIRECTORIES as $base) {
            $baseDirectory = rtrim($shopRoot, '/') . '/' . $base;
            if (!is_dir($baseDirectory)) {
                continue;
            }

            $depth = strpos($base, 'engine/Shopware/Plugins/') === 0 ? 2 : 1;
            foreach ($this->pluginDirectories($baseDirectory, $base, $depth, $complete) as $plugin => $directory) {
                $target = realpath($directory);
                if (basename($plugin) === $ownPlugin || $target === false || isset($visited[$target])) {
                    $complete = $complete && $target !== false;
                    continue;
                }
                $visited[$target] = true;

                foreach ($this->phpFiles($directory, $complete) as $file) {
                    if (++$files > self::MAX_FILES) {
                        $complete = false;
                        break 3;
                    }
                    if (isset($found[$plugin]) || $file->getSize() > self::MAX_FILE_SIZE) {
                        continue;
                    }

                    $code = @file_get_contents($file->getPathname());
                    if (!\is_string($code)) {
                        $complete = false;
                    } elseif (preg_match(self::PATTERN, $code) === 1) {
                        $found[$plugin] = true;
                    }
                }
            }
        }

        $plugins = array_keys($found);
        sort($plugins);

        return ['plugins' => $plugins, 'complete' => $complete];
    }

    /**
     * Plugin = first directory below the plugin base (e.g. custom/plugins/Example);
     * legacy plugins sit one level deeper (Community/Frontend/Example).
     * Directories given as symbolic links are included.
     *
     * @return array<string, string> plugin name => directory path
     */
    private function pluginDirectories(string $baseDirectory, string $base, int $depth, bool &$complete): array
    {
        $directories = [$base => $baseDirectory];
        for ($level = 0; $level < $depth; ++$level) {
            $children = [];
            foreach ($directories as $name => $directory) {
                $entries = @scandir($directory);
                if (!\is_array($entries)) {
                    $complete = false;
                    continue;
                }
                foreach ($entries as $entry) {
                    $path = $directory . '/' . $entry;
                    if ($entry !== '.' && $entry !== '..' && is_dir($path)) {
                        $children[$name . '/' . $entry] = $path;
                    }
                }
            }
            $directories = $children;
        }

        return $directories;
    }

    /**
     * PHP files below $directory. Unreadable subdirectories are skipped and
     * mark the scan as incomplete.
     *
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $directory, bool &$complete): array
    {
        $files = [];
        $readable = static function (SplFileInfo $file) use (&$complete): bool {
            if (!$file->isDir() || $file->isLink()) {
                return true;
            }
            if (\in_array(strtolower($file->getFilename()), self::SKIPPED_DIRECTORIES, true)) {
                return false;
            }
            if (!$file->isReadable() || !$file->isExecutable()) {
                $complete = false;

                return false;
            }

            return true;
        };

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), $readable),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file;
                }
            }
        } catch (Throwable $exception) {
            // Unreadable plugin directory: check what was found so far.
            $complete = false;
        }

        return $files;
    }
}
