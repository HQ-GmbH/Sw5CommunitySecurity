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
 * Checks whether the table of the "one login per customer account" option
 * exists.
 *
 * Usage: php Tests/Integration/login-table.php <shopware-root> <present|absent>
 */

if ($argc !== 3 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[2], ['present', 'absent'], true)) {
    fwrite(STDERR, "Usage: php login-table.php <shopware-root> <present|absent>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

$present = $kernel->getContainer()->get('db')->fetchOne("SHOW TABLES LIKE 's\\_plugin\\_sw5cs\\_customer\\_login'") !== false;

if ($present !== ($argv[2] === 'present')) {
    fwrite(STDERR, \sprintf("FAIL: table s_plugin_sw5cs_customer_login expected %s.\n", $argv[2]));
    exit(1);
}

echo \sprintf("  ok: table s_plugin_sw5cs_customer_login %s\n", $argv[2]);
