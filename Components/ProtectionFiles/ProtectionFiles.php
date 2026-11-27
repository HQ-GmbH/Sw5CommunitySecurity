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

namespace Sw5CommunitySecurity\Components\ProtectionFiles;

/**
 * Apache access rules (.htaccess) that Shopware ships in internal
 * directories (Shopware 5.6.10 and 5.7.20: bin, engine/Library, files/backup,
 * files/documents, media/temp, var/cache, var/log, vendor).
 *
 * Shopware itself never deletes these files: cache clearing removes the
 * environment directories below var/cache only. They get lost through
 * deployments or uploads that skip dot files, or when vendor/ is rebuilt.
 *
 * restore() only creates files that are missing in existing directories.
 * Existing files are never changed, whatever their content, and the file is
 * created exclusively. A symbolic link to an existing file counts as present;
 * a dangling one counts as missing but is never followed or replaced.
 */
final class ProtectionFiles
{
    public const FILE_NAME = '.htaccess';

    /**
     * Directory => rule. engine/Library also contains the Ext JS backend and
     * other assets loaded by browsers, so only PHP files are denied there.
     */
    public const DIRECTORIES = [
        'bin' => self::RULE_DENY_ALL,
        'engine/Library' => self::RULE_DENY_PHP,
        'files/backup' => self::RULE_DENY_ALL,
        'files/documents' => self::RULE_DENY_ALL,
        'media/temp' => self::RULE_DENY_ALL,
        'var/cache' => self::RULE_DENY_ALL,
        'var/log' => self::RULE_DENY_ALL,
        'vendor' => self::RULE_DENY_ALL,
    ];

    public const RULE_DENY_ALL = 'deny-all';
    public const RULE_DENY_PHP = 'deny-php';

    private const HEADER = "# Restored by Community Security Guard for Shopware 5.\n"
        . "# Blocks direct web access; Apache 2.4 (mod_authz_core) and Apache 2.2.\n";

    private const DENY = "<IfModule mod_authz_core.c>\n"
        . "    Require all denied\n"
        . "</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n"
        . "    Deny from all\n"
        . "</IfModule>\n";

    private const DENY_PHP = "<FilesMatch \"\\.php$\">\n"
        . "    <IfModule mod_authz_core.c>\n"
        . "        Require all denied\n"
        . "    </IfModule>\n"
        . "    <IfModule !mod_authz_core.c>\n"
        . "        Deny from all\n"
        . "    </IfModule>\n"
        . "</FilesMatch>\n";

    /**
     * Relative paths of missing protection files in existing directories.
     *
     * @return list<string>
     */
    public function missing(string $shopwareRoot): array
    {
        $root = rtrim($shopwareRoot, '/');
        $missing = [];
        foreach (array_keys(self::DIRECTORIES) as $directory) {
            $path = $root . '/' . $directory . '/' . self::FILE_NAME;
            if (is_dir($root . '/' . $directory) && !is_file($path)) {
                $missing[] = $directory . '/' . self::FILE_NAME;
            }
        }

        return $missing;
    }

    public function restore(string $shopwareRoot): RestoreResult
    {
        $root = rtrim($shopwareRoot, '/');
        $restored = [];
        $failed = [];
        foreach (self::DIRECTORIES as $directory => $rule) {
            $relative = $directory . '/' . self::FILE_NAME;
            $path = $root . '/' . $relative;
            if (!is_dir($root . '/' . $directory) || is_file($path)) {
                continue;
            }

            // A dangling symbolic link: PHP resolves links before opening, so
            // "x" alone would create the link target. It is never written.
            if (!is_link($path) && $this->create($path, self::content($rule))) {
                $restored[] = $relative;
            } else {
                $failed[] = $relative;
            }
        }

        return new RestoreResult($restored, $failed);
    }

    /**
     * @param self::RULE_* $rule
     */
    public static function content(string $rule): string
    {
        return self::HEADER . ($rule === self::RULE_DENY_PHP ? self::DENY_PHP : self::DENY);
    }

    private function create(string $path, string $content): bool
    {
        // "x": fails if the file was created in the meantime.
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        $written = fwrite($handle, $content);
        $closed = fclose($handle);
        if ($written !== \strlen($content) || !$closed) {
            // Remove the incomplete file. It was created exclusively by this
            // call, so if removing fails, completing it is safe.
            if (@unlink($path)) {
                return false;
            }

            return file_put_contents($path, $content, \LOCK_EX) === \strlen($content);
        }

        return true;
    }
}
