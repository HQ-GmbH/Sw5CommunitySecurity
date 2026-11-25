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
 * Checks whether a plugin table exists.
 *
 * Usage: php Tests/Integration/table.php <shopware-root> <table> <present|absent>
 */

if ($argc !== 4 || !is_file($argv[1] . '/autoload.php') || preg_match('/^s_plugin_sw5cs_[a-z_]+$/', $argv[2]) !== 1 || !\in_array($argv[3], ['present', 'absent'], true)) {
    fwrite(STDERR, "Usage: php table.php <shopware-root> <s_plugin_sw5cs_...> <present|absent>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

// "dbal_connection" like the other scripts: fetching "db" first in a CLI kernel runs into a circular reference.
$present = $kernel->getContainer()->get('dbal_connection')->fetchColumn('SHOW TABLES LIKE ?', [str_replace('_', '\\_', $argv[2])]) !== false;

if ($present !== ($argv[3] === 'present')) {
    fwrite(STDERR, \sprintf("FAIL: table %s expected %s.\n", $argv[2], $argv[3]));
    exit(1);
}

echo \sprintf("  ok: table %s %s\n", $argv[2], $argv[3]);
