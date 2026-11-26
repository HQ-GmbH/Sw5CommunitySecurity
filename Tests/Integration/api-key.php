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
 * Gives a backend user of the test shop a random REST API key ("set", the key
 * is printed) or removes it again ("remove").
 *
 * Uses DBAL methods available in Shopware 5.6 (DBAL 2.6) and 5.7 (DBAL 2.13).
 *
 * Usage: php Tests/Integration/api-key.php <shopware-root> <username> set|remove
 */

if ($argc !== 4 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[3], ['set', 'remove'], true)) {
    fwrite(STDERR, "Usage: php api-key.php <shopware-root> <username> set|remove\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$connection = $kernel->getContainer()->get('dbal_connection');

$key = $argv[3] === 'set' ? bin2hex(random_bytes(20)) : null;
$updated = $connection->executeUpdate('UPDATE s_core_auth SET apiKey = ? WHERE username = ?', [$key, $argv[2]]);
if ($updated !== 1) {
    fwrite(STDERR, "User not found: {$argv[2]}\n");
    exit(1);
}

echo $key ?? '', \PHP_EOL;
