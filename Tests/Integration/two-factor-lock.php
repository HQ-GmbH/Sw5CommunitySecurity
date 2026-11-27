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

/*
 * Prints the remaining seconds of the plugin's two-factor lock of a backend
 * user (0 = not locked). With "unlock", lifts Shopware's own login lock of the
 * test user instead, so the test can log in again after the lock checks.
 *
 * Usage: php Tests/Integration/two-factor-lock.php <shopware-root> <username> [unlock]
 */

if (($argc !== 3 && !($argc === 4 && $argv[3] === 'unlock')) || !is_file($argv[1] . '/autoload.php') || preg_match('/^[A-Za-z0-9_-]+$/', $argv[2]) !== 1) {
    fwrite(STDERR, "Usage: php two-factor-lock.php <shopware-root> <username> [unlock]\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

$connection = $kernel->getContainer()->get('dbal_connection');
if ($argc === 4) {
    // A date in the past, not NULL: Shopware 5.6 checks "lockeduntil <= NOW()", which is never true for NULL.
    $connection->executeUpdate("UPDATE s_core_auth SET lockeduntil = '2000-01-01 00:00:00', failedlogins = 0 WHERE username = ?", [$argv[2]]);
    exit(0);
}

$lockedUntil = $connection->fetchColumn(
    'SELECT UNIX_TIMESTAMP(t.locked_until) FROM s_plugin_sw5cs_backend_two_factor t'
    . ' INNER JOIN s_core_auth a ON a.id = t.user_id WHERE a.username = ?',
    [$argv[2]],
);

echo is_numeric($lockedUntil) ? max(0, (int) $lockedUntil - time()) : 0, "\n";
