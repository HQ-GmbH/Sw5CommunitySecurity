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
 * Test fixture for the integration test: lets the footer snippet
 * "IndexCopyright" of the default shop print $smarty.request.sSearch, like
 * third-party templates that read the superglobal $_REQUEST.
 *
 * Usage: php request-snippet.php <shopware-root> set|restore <state-file>
 */

if ($argc !== 4 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[2], ['set', 'restore'], true)) {
    fwrite(STDERR, "Usage: php request-snippet.php <shopware-root> set|restore <state-file>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

/** @var Doctrine\DBAL\Connection $connection */
$connection = $kernel->getContainer()->get('dbal_connection');
$localeId = (int) $connection->fetchColumn('SELECT locale_id FROM s_core_shops WHERE `default` = 1');
$key = ['namespace' => 'frontend/index/footer', 'shopID' => 1, 'localeID' => $localeId, 'name' => 'IndexCopyright'];
$where = 'namespace = :namespace AND shopID = :shopID AND localeID = :localeID AND name = :name';

if ($argv[2] === 'set') {
    $original = $connection->fetchColumn('SELECT value FROM s_core_snippets WHERE ' . $where, $key);
    file_put_contents($argv[3], json_encode(['value' => $original === false ? null : $original]));
    $connection->executeUpdate('DELETE FROM s_core_snippets WHERE ' . $where, $key);
    $connection->insert('s_core_snippets', $key + [
        'value' => 'sw5cs-request[{$smarty.request.sSearch|escape}]',
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s'),
        'dirty' => 1,
    ]);
    echo "set\n";
    exit(0);
}

$state = json_decode((string) file_get_contents($argv[3]), true);
$connection->executeUpdate('DELETE FROM s_core_snippets WHERE ' . $where, $key);
if (\is_array($state) && \is_string($state['value'] ?? null)) {
    $connection->insert('s_core_snippets', $key + [
        'value' => $state['value'],
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s'),
        'dirty' => 0,
    ]);
}
echo "restored\n";
