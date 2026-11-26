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
 * Integration checks for the content check (editor content with script).
 *
 *  - "doctrine": saves shop pages and categories through the entity manager
 *    (as the REST API and other plugins do); content with script must be
 *    rejected, ordinary HTML saved.
 *  - "fixture contains <text>": exit code 0 if the test translation
 *    (language FIXTURE_LANGUAGE) contains the text.
 *  - "fixture add|remove": writes a shop page and a translation with
 *    markers directly to the database (bypassing the check) for the scan
 *    command, or restores them.
 *
 * Only harmless markers are used; nothing is ever executed.
 *
 * Usage: php Tests/Integration/content-guard.php <shopware-root> doctrine|fixture add|fixture remove|fixture contains <text>
 */

use Shopware\Models\Category\Category;
use Shopware\Models\Site\Site;
use Sw5CommunitySecurity\Components\ContentGuard\UnsafeContentException;

const FIXTURE_SITE_ID = 2;
const FIXTURE_LANGUAGE = '9998';

$mode = $argv[2] ?? '';
$fixtureAction = $argv[3] ?? '';
if (!isset($argv[1]) || !is_file($argv[1] . '/autoload.php') || !($mode === 'doctrine' || ($mode === 'fixture' && \in_array($fixtureAction, ['add', 'remove', 'contains'], true)))) {
    fwrite(STDERR, "Usage: php content-guard.php <shopware-root> doctrine|fixture add|fixture remove|fixture contains <text>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$container = $kernel->getContainer();

if ($mode === 'fixture') {
    $connection = $container->get('dbal_connection');
    $originalFile = sys_get_temp_dir() . '/sw5cs-content-fixture-site.txt';
    if ($fixtureAction === 'contains') {
        $data = (string) $connection->fetchColumn('SELECT objectdata FROM s_core_translations WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?', ['page', 1, FIXTURE_LANGUAGE]);
        exit(strpos($data, (string) ($argv[4] ?? '')) !== false && ($argv[4] ?? '') !== '' ? 0 : 1);
    }
    $connection->executeUpdate('DELETE FROM s_core_translations WHERE objecttype = ? AND objectlanguage = ?', ['page', FIXTURE_LANGUAGE]);
    if ($fixtureAction === 'add') {
        file_put_contents($originalFile, (string) $connection->fetchColumn('SELECT html FROM s_cms_static WHERE id = ?', [FIXTURE_SITE_ID]));
        $connection->executeUpdate('UPDATE s_cms_static SET html = ? WHERE id = ?', ['<p>sw5cs</p><img src="sw5cs-marker.png" onerror="sw5csMarker()">', FIXTURE_SITE_ID]);
        $connection->insert('s_core_translations', [
            'objecttype' => 'page',
            'objectkey' => FIXTURE_SITE_ID,
            'objectlanguage' => FIXTURE_LANGUAGE,
            'objectdata' => serialize(['html' => '<a href="javascript:sw5csMarker()">sw5cs</a>']),
            'dirty' => 0,
        ]);
    } elseif (is_file($originalFile)) {
        $connection->executeUpdate('UPDATE s_cms_static SET html = ? WHERE id = ?', [(string) file_get_contents($originalFile), FIXTURE_SITE_ID]);
        unlink($originalFile);
    }
    echo "  ok: fixture {$fixtureAction}\n";
    exit(0);
}

/**
 * Runs $change against a fresh entity manager state and reports whether the
 * flush was rejected by the content check.
 */
function attempt(Closure $change): string
{
    global $container;

    $container->reset('models');
    $entityManager = $container->get('models');

    try {
        $change($entityManager);
        $entityManager->flush();

        return 'saved';
    } catch (Throwable $exception) {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof UnsafeContentException) {
                return 'rejected';
            }
        }

        return 'error: ' . \get_class($exception) . ': ' . $exception->getMessage();
    }
}

$checks = [
    'shop page with ordinary HTML is saved' => [
        'saved',
        static function ($entityManager): void {
            $site = $entityManager->find(Site::class, 1);
            $site->setHtml('<p><strong>sw5cs</strong> <a href="https://example.org/">Link</a></p><iframe src="https://www.youtube-nocookie.com/embed/sw5cs"></iframe>');
        },
    ],
    'shop page with a javascript: address in an iframe is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $site = $entityManager->find(Site::class, 1);
            $site->setHtml('<iframe src="javascript:sw5csMarker()"></iframe>');
        },
    ],
    'category text with an event handler is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $category = $entityManager->getRepository(Category::class)->findOneBy(['parentId' => 1]);
            $category->setCmsText('<img src="sw5cs-marker.png" onerror="sw5csMarker()">');
        },
    ],
];

$failed = false;
foreach ($checks as $label => [$expected, $change]) {
    $actual = attempt($change);
    $ok = $actual === $expected;
    $failed = $failed || !$ok;
    echo ($ok ? '  ok: ' : 'FAIL: ') . $label . ' (expected ' . $expected . ', got ' . $actual . ')' . \PHP_EOL;
}

exit($failed ? 1 : 0);
