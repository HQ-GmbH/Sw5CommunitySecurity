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
 * Prints the id of a configuration element of this plugin, needed to save
 * the plugin configuration through the backend like the plugin manager does.
 *
 * Usage: php Tests/Integration/config-element-id.php <shopware-root> <element-name>
 */

if ($argc !== 3 || !is_file($argv[1] . '/autoload.php')) {
    fwrite(STDERR, "Usage: php config-element-id.php <shopware-root> <element-name>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

$id = $kernel->getContainer()->get('dbal_connection')->fetchColumn(
    'SELECT e.id FROM s_core_config_elements e'
    . ' INNER JOIN s_core_config_forms f ON f.id = e.form_id'
    . ' INNER JOIN s_core_plugins p ON p.id = f.plugin_id'
    . ' WHERE p.name = ? AND e.name = ?',
    ['Sw5CommunitySecurity', $argv[2]]
);

if ($id === false) {
    fwrite(STDERR, "Configuration element not found.\n");
    exit(1);
}

echo $id;
