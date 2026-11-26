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
 * {$value|escape:'javascript'} and {$value|escape:$type} (type known only at
 * runtime) with Shopware's template engine, as mails, documents and themes
 * do, and compares the output with the expected escaping. Other escape types
 * given at runtime must keep Smarty's behaviour. The value is a harmless marker; nothing is executed.
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
    'escape type from a variable' => '{$sw5csValue|escape:$sw5csType}',
];
foreach ($templates as $label => $source) {
    $template = $container->get('template');
    $template->assign('sw5csValue', $marker);
    $template->assign('sw5csType', 'javascript');
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

// A modifier "escape" registered as object method, as the product export does
// (engine/Shopware/Core/sExport.php, sEscapeString). Its own "javascript" case
// uses the list of Smarty 3.1.12, like the stand-in below.
$exporter = new class {
    public function sEscapeString($string, $esc_type = '', $char_set = null)
    {
        if ($esc_type === 'javascript') {
            return strtr($string, ['\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\r" => '\\r', "\n" => '\\n', '</' => '<\/']);
        }

        return 'export:' . $esc_type . ':' . $string;
    }
};
$exportTemplate = clone $container->get('template');
$exportTemplate->compile_id = 'sw5cs_export_' . $argv[2];
$exportTemplate->registerPlugin('modifier', 'escape', [$exporter, 'sEscapeString']);
$exportTemplate->assign('sw5csValue', $marker);
$actual = $exportTemplate->fetch("string:{\$sw5csValue|escape:'javascript'}");
$ok = $actual === $expected;
$failed = $failed || !$ok;
printf("  %s: registered escape method (product export), %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $argv[2], $expected, $actual);
$actual = $exportTemplate->fetch("string:{\$sw5csValue|escape:'csv'}");
$ok = $actual === 'export:csv:' . $marker;
$failed = $failed || !$ok;
printf("  %s: registered escape method, other types unchanged (got %s)\n", $ok ? 'ok' : 'FAIL', $actual);

// A modifier "escape" registered as static method by another plugin: its own
// result is kept; for "javascript" it is completed to the full list.
if (!class_exists('Sw5csIntegrationEscaper', false)) {
    eval('class Sw5csIntegrationEscaper { public static function escape($string, $type = "html") { return $type === "javascript" ? strtr($string, array("\\\\" => "\\\\\\\\", "\'" => "\\\\\'", "\"" => "\\\\\"", "\r" => "\\\\r", "\n" => "\\\\n", "</" => "<\\/")) : "static:" . $type . ":" . $string; } }');
}
$staticTemplate = clone $container->get('template');
$staticTemplate->compile_id = 'sw5cs_static_' . $argv[2];
$staticTemplate->registerPlugin('modifier', 'escape', ['Sw5csIntegrationEscaper', 'escape']);
$staticTemplate->assign('sw5csValue', $marker);
$actual = $staticTemplate->fetch("string:{\$sw5csValue|escape:'javascript'}");
$ok = $actual === $expected;
$failed = $failed || !$ok;
printf("  %s: escape registered as static method, %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $argv[2], $expected, $actual);
$actual = $staticTemplate->fetch("string:{\$sw5csValue|escape:'url'}");
$ok = $actual === 'static:url:' . $marker;
$failed = $failed || !$ok;
printf("  %s: escape registered as static method, other types unchanged (got %s)\n", $ok ? 'ok' : 'FAIL', $actual);

// Nocache section: only with Smarty's output cache, which Shopware never
// switches on; a separate copy of the engine with its own cache directory.
$cacheDirectory = sys_get_temp_dir() . '/sw5cs-nocache-' . bin2hex(random_bytes(4));
$cachedTemplate = clone $container->get('template');
$cachedTemplate->compile_id = 'sw5cs_nocache_' . $argv[2];
$cachedTemplate->setCacheDir($cacheDirectory);
$cachedTemplate->caching = 1;
$cachedTemplate->assign('sw5csValue', $marker);
$cachedTemplate->assign('sw5csType', 'javascript');
$actual = $cachedTemplate->fetch('string:{$sw5csValue|escape:$sw5csType nocache}');
$ok = $actual === $expected;
$failed = $failed || !$ok;
printf("  %s: nocache section with escape type from a variable, %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $argv[2], $expected, $actual);
array_map('unlink', glob($cacheDirectory . '/*') ?: []);
@rmdir($cacheDirectory);

// Other runtime escape types are passed on to Smarty unchanged.
$template = $container->get('template');
$template->assign('sw5csValue', '<b title="sw5cs">');
$template->assign('sw5csType', 'html');
$actual = $template->fetch('string:{$sw5csValue|escape:$sw5csType}');
$ok = $actual === '&lt;b title=&quot;sw5cs&quot;&gt;';
$failed = $failed || !$ok;
printf("  %s: html escape type from a variable is unchanged (got %s)\n", $ok ? 'ok' : 'FAIL', $actual);

exit($failed ? 1 : 0);
