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
 * Checks whether the ACL resource of the backend module "Security check"
 * exists in the database.
 *
 * Usage: php Tests/Integration/acl-resource.php <shopware-root> <present|absent>
 */

if ($argc !== 3 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[2], ['present', 'absent'], true)) {
    fwrite(STDERR, "Usage: php acl-resource.php <shopware-root> <present|absent>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

$connection = $kernel->getContainer()->get('dbal_connection');
$resourceId = $connection->fetchColumn('SELECT id FROM s_core_acl_resources WHERE name = ?', ['sw5cssecuritycheck']);
$present = $resourceId !== false;

if ($present && (int) $connection->fetchColumn('SELECT COUNT(*) FROM s_core_acl_privileges WHERE resourceID = ? AND name = ?', [$resourceId, 'read']) !== 1) {
    fwrite(STDERR, "FAIL: ACL resource sw5cssecuritycheck has no privilege \"read\".\n");
    exit(1);
}

if ($present !== ($argv[2] === 'present')) {
    fwrite(STDERR, \sprintf("FAIL: ACL resource sw5cssecuritycheck expected %s.\n", $argv[2]));
    exit(1);
}

echo \sprintf("  ok: ACL resource sw5cssecuritycheck %s\n", $argv[2]);
