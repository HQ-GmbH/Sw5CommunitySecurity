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
 * Integration check for the JavaScript escaping module: renders
 * {$value|escape:'javascript'} with Shopware's template engine, as mails,
 * documents and themes do, and compares the output with the expected
 * escaping. The value is a harmless marker; nothing is executed.
 *
 * Usage: php Tests/Integration/javascript-escape.php <shopware-root> <hardened|legacy>
 *   hardened: replacements of Smarty 3.1.48 (module active)
 *   legacy:   replacements of Smarty 3.1.12 (module disabled or plugin inactive)
 */

if ($argc !== 3 || !is_file($argv[1] . '/autoload.php') || !\in_array($argv[2], ['hardened', 'legacy'], true)) {
    fwrite(STDERR, "Usage: php javascript-escape.php <shopware-root> <hardened|legacy>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$container = $kernel->getContainer();

$marker = "sw5cs '\"\\ </p> <!-- note <span> `tpl` \${marker}";
$expected = [
    'hardened' => "sw5cs \\'\\\"\\\\ <\\/p> <\\!-- note <\\span> \\`tpl\\` \\\$\\{marker}",
    'legacy' => "sw5cs \\'\\\"\\\\ <\\/p> <!-- note <span> `tpl` \${marker}",
][$argv[2]];

$failed = false;
$templates = [
    'single-quoted escape type' => "{\$sw5csValue|escape:'javascript'}",
    'double-quoted escape type' => '{$sw5csValue|escape:"javascript"}',
];
foreach ($templates as $label => $source) {
    $template = $container->get('template');
    $template->assign('sw5csValue', $marker);
    $actual = $template->fetch('string:' . $source);
    $ok = $actual === $expected;
    $failed = $failed || !$ok;
    printf("  %s: %s, %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $label, $argv[2], $expected, $actual);
}

// Mails use the same engine through Shopware's string compiler.
$compiler = new Shopware_Components_StringCompiler($container->get('template'));
$actual = $compiler->compileString("{\$sw5csValue|escape:'javascript'}", ['sw5csValue' => $marker]);
$ok = $actual === $expected;
$failed = $failed || !$ok;
printf("  %s: mail string compiler, %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $argv[2], $expected, $actual);

exit($failed ? 1 : 0);
