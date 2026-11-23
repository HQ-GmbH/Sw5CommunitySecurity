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
 * Adds or removes a stored document box translation containing a harmless
 * {include} marker and a snippet with a harmless static class marker directly
 * in the database, bypassing the template guard,
 * so the scan command can be tested against existing content.
 *
 * Uses DBAL methods available in Shopware 5.6 (DBAL 2.6) and 5.7 (DBAL 2.13).
 *
 * Usage: php Tests/Integration/scan-fixture.php <shopware-root> add|remove
 */

if ($argc !== 3 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[2], ['add', 'remove'], true)) {
    fwrite(STDERR, "Usage: php scan-fixture.php <shopware-root> add|remove\n");
    exit(2);
}

const FIXTURE_LANGUAGE = '9999';

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$connection = $kernel->getContainer()->get('dbal_connection');

$connection->executeUpdate(
    'DELETE FROM s_core_translations WHERE objecttype = ? AND objectlanguage = ?',
    ['documents', FIXTURE_LANGUAGE],
);
$connection->executeUpdate('DELETE FROM s_core_snippets WHERE namespace = ?', ['sw5cs/scan-fixture']);

if ($argv[2] === 'add') {
    $connection->insert('s_core_translations', [
        'objecttype' => 'documents',
        'objectkey' => 1,
        'objectlanguage' => FIXTURE_LANGUAGE,
        'objectdata' => serialize([1 => ['Body_Value' => '{include file=$sw5csScanMarker}', 'Body_Style' => 'color: #000;']]),
        'dirty' => 0,
    ]);
    $connection->insert('s_core_snippets', [
        'namespace' => 'sw5cs/scan-fixture',
        'name' => 'marker',
        'shopID' => 1,
        'localeID' => 1,
        'value' => '{Sw5csScanMarker::VALUE}',
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s'),
        'dirty' => 0,
    ]);
}

echo $argv[2] === 'add' ? "  fixture added\n" : "  fixture removed\n";
