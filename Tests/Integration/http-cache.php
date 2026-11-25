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
 * Prints "enabled" or "disabled" depending on Shopware's HTTP cache. With the
 * HTTP cache, HttpCache\SubRequestHandler removes forwarding headers from
 * untrusted addresses before a controller sees them.
 *
 * Usage: php Tests/Integration/http-cache.php <shopware-root>
 */

if ($argc !== 2 || !is_file($argv[1] . '/autoload.php')) {
    fwrite(STDERR, "Usage: php http-cache.php <shopware-root>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();

echo $kernel->getContainer()->getParameter('shopware.httpcache.enabled') ? 'enabled' : 'disabled';
