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
 * Checks the components of a CycloneDX SBOM (bin/build-sbom.php) against the
 * OSV vulnerability database (https://osv.dev, batch query API).
 *
 * Only the package URLs of the components (package name and version) are
 * sent; the plugin itself has no package URL in OSV and is skipped. Code
 * fragments carry their origin only as pedigree without a package URL and are
 * not queried, as they are not the complete upstream library.
 *
 * Exit code 0: no known vulnerability; 1: vulnerabilities found or the query
 * failed after several attempts.
 * Usage: php bin/check-sbom-vulnerabilities.php build/Sw5CommunitySecurity-<version>.cdx.json
 */

const OSV_BATCH_URL = 'https://api.osv.dev/v1/querybatch';
const OSV_VULNERABILITY_URL = 'https://osv.dev/vulnerability/';
const ATTEMPTS = 3;

/**
 * @return never
 */
function fail(string $message): void
{
    fwrite(\STDERR, $message . \PHP_EOL);
    exit(1);
}

/**
 * @param list<string> $purls
 */
function queryOsv(array $purls): string
{
    $queries = array_map(static fn (string $purl): array => ['package' => ['purl' => $purl]], $purls);
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nUser-Agent: Sw5CommunitySecurity-SBOM-Check\r\n",
        'content' => json_encode(['queries' => $queries], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $lastError = '';
    for ($attempt = 1; $attempt <= ATTEMPTS; ++$attempt) {
        $response = @file_get_contents(OSV_BATCH_URL, false, $context);
        $status = isset($http_response_header[0]) ? $http_response_header[0] : 'no response';
        if (\is_string($response) && str_contains($status, ' 200')) {
            return $response;
        }
        $lastError = $status;
        if ($attempt < ATTEMPTS) {
            sleep(5 * $attempt);
        }
    }

    fail('OSV query failed after ' . ATTEMPTS . ' attempts: ' . $lastError);
}

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fail('Usage: php bin/check-sbom-vulnerabilities.php <sbom.cdx.json>');
}

$bom = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
if (!\is_array($bom) || !isset($bom['components']) || !\is_array($bom['components'])) {
    fail($file . ' has no components.');
}

$purls = [];
foreach ($bom['components'] as $component) {
    if (\is_array($component) && isset($component['purl']) && \is_string($component['purl'])) {
        $purls[] = $component['purl'];
    }
}
if ($purls === []) {
    fail($file . ' has no components with a package URL.');
}

$answer = json_decode(queryOsv($purls), true, 512, \JSON_THROW_ON_ERROR);
if (!\is_array($answer) || !isset($answer['results']) || !\is_array($answer['results']) || \count($answer['results']) !== \count($purls)) {
    fail('Unexpected answer from OSV.');
}

$found = 0;
$results = array_values($answer['results']);
foreach ($purls as $index => $purl) {
    $result = $results[$index] ?? null;
    $vulnerabilities = \is_array($result) && isset($result['vulns']) && \is_array($result['vulns']) ? $result['vulns'] : [];
    if ($vulnerabilities === []) {
        echo 'ok: ', $purl, \PHP_EOL;
        continue;
    }
    foreach ($vulnerabilities as $vulnerability) {
        $id = \is_array($vulnerability) && isset($vulnerability['id']) && \is_string($vulnerability['id']) ? $vulnerability['id'] : '?';
        echo 'VULNERABLE: ', $purl, ' ', OSV_VULNERABILITY_URL, $id, \PHP_EOL;
        ++$found;
    }
}

if ($found > 0) {
    fail($found . ' known vulnerabilities in the SBOM components.');
}
echo 'No known vulnerabilities in ', \count($purls), ' components (OSV, ', gmdate('Y-m-d'), ').', \PHP_EOL;
