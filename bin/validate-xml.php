<?php

declare(strict_types=1);

/*
 * Validates the plugin's XML files against the schemas shipped with a
 * Shopware 5 installation, the same way Shopware does it at runtime
 * (DOMDocument::schemaValidate via libxml2).
 *
 * The schemas are not part of this repository (Shopware is AGPL-licensed);
 * they are read from the given Shopware root directory.
 *
 * Usage: php bin/validate-xml.php <shopware-root>
 */

if ($argc !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "Usage: php bin/validate-xml.php <shopware-root>\n");
    exit(2);
}

$pluginRoot = dirname(__DIR__);
$shopwareRoot = rtrim($argv[1], '/');

$checks = [
    'plugin.xml' => $shopwareRoot . '/engine/Shopware/Components/Plugin/schema/plugin.xsd',
    'Resources/config.xml' => $shopwareRoot . '/engine/Shopware/Components/Plugin/schema/config.xsd',
    'Resources/services.xml' => $shopwareRoot . '/vendor/symfony/dependency-injection/Loader/schema/dic/services/services-1.0.xsd',
];

libxml_use_internal_errors(true);
printf("libxml2 %s\n", LIBXML_DOTTED_VERSION);

$failed = false;

foreach ($checks as $file => $schema) {
    if (!is_file($schema)) {
        fwrite(STDERR, sprintf("%s: schema not found at %s\n", $file, $schema));
        $failed = true;
        continue;
    }

    $document = new DOMDocument();
    if ($document->load($pluginRoot . '/' . $file) === false) {
        $failed = true;
        reportErrors($file, 'not well-formed');
        continue;
    }

    if (@$document->schemaValidate($schema)) {
        printf("%s: valid against %s\n", $file, basename($schema));
        libxml_clear_errors();
        continue;
    }

    $failed = true;
    reportErrors($file, 'INVALID against ' . basename($schema));
}

exit($failed ? 1 : 0);

function reportErrors(string $file, string $summary): void
{
    fwrite(STDERR, sprintf("%s: %s\n", $file, $summary));

    foreach (libxml_get_errors() as $error) {
        fwrite(STDERR, sprintf("  line %d: %s\n", $error->line, trim($error->message)));
    }

    libxml_clear_errors();
}
