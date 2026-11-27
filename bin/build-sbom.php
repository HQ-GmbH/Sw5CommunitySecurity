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
 * Builds the CycloneDX 1.6 SBOM (JSON) of a release.
 *
 * The SBOM describes what the release ZIP of a git revision ships:
 *  - the plugin itself and its runtime Composer requirements, as reported by
 *    the CycloneDX Composer plugin (development dependencies are omitted);
 *  - every bundled library in Resources/views/backend/<module>/lib/<name>/,
 *    read from its ORIGIN.md (version, package URL, SPDX license, SHA-256);
 *  - code fragments taken from third-party projects (see BUNDLED_FRAGMENTS).
 *
 * Every entry of THIRD-PARTY-NOTICES.md, section "Im Plugin enthalten", must
 * be covered by a component, otherwise the script fails. The result is
 * validated against the CycloneDX 1.6 JSON schema.
 *
 * Needs the development dependencies (composer install).
 * Usage: php bin/build-sbom.php [git-revision]
 * Prints the path of the written file: build/Sw5CommunitySecurity-<version>.cdx.json
 */

use CycloneDX\Core\Spec\Version;
use CycloneDX\Core\Validation\Validators\JsonStrictValidator;

const PLUGIN_NAME = 'Sw5CommunitySecurity';
const REPOSITORY_URL = 'https://github.com/HQ-GmbH/Sw5CommunitySecurity';
const LIBRARY_PATTERN = '#^Resources/views/backend/[^/]+/lib/([^/]+)/ORIGIN\.md$#';
const NOTICES_SECTION = '## Im Plugin enthalten';

/**
 * Third-party code fragments inside our own files. Keep in sync with
 * THIRD-PARTY-NOTICES.md; the coverage check below enforces it.
 */
const BUNDLED_FRAGMENTS = [
    [
        'bom-ref' => 'bundled:smarty-escape-javascript-lists',
        'type' => 'library',
        'name' => 'smarty-escape-javascript-replacement-lists',
        'description' => 'Replacement lists of the Smarty modifier escape:\'javascript\' (libs/plugins/modifiercompiler.escape.php): the list emitted by 3.1.12, to recognise it in compiled templates, and the values of v3.1.48. Code fragments only, not the Smarty library.',
        'copyright' => 'New Digital Group, Inc., Uwe Tews (author Rodney Rehm)',
        'license' => 'LGPL-3.0-or-later',
        'files' => ['Components/JavaScriptEscape/JavaScriptEscapeHardening.php'],
        'ancestors' => [
            ['name' => 'smarty', 'version' => 'v3.1.12', 'purl' => 'pkg:composer/smarty/smarty@v3.1.12'],
            ['name' => 'smarty', 'version' => 'v3.1.48', 'purl' => 'pkg:composer/smarty/smarty@v3.1.48'],
        ],
        'vcs' => 'https://github.com/smarty-php/smarty',
    ],
];

/**
 * @param list<string> $command
 */
function run(array $command): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!\is_resource($process) || !isset($pipes[1], $pipes[2])) {
        fail('Could not start: ' . implode(' ', $command));
    }
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        fail(implode(' ', $command) . " failed:\n" . $errors . $output);
    }

    return $output;
}

/**
 * @return never
 */
function fail(string $message): void
{
    fwrite(\STDERR, $message . \PHP_EOL);
    exit(1);
}

function gitShow(string $commit, string $path): string
{
    return run(['git', 'show', $commit . ':' . $path]);
}

function match1(string $pattern, string $subject, string $what): string
{
    if (preg_match($pattern, $subject, $match) !== 1 || !isset($match[1])) {
        fail('Could not read ' . $what . '.');
    }

    return $match[1];
}

/**
 * @return array<mixed>
 */
function decodeObject(string $json, string $what): array
{
    $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    if (!\is_array($data)) {
        fail($what . ' is not a JSON object.');
    }

    return $data;
}

/**
 * @param array<mixed> $data
 *
 * @return mixed
 */
function field(array $data, string $key)
{
    if (!\array_key_exists($key, $data)) {
        fail('Missing "' . $key . '" in the SBOM.');
    }

    return $data[$key];
}

/**
 * Object or list field; optional fields that are missing yield [].
 *
 * @param array<mixed> $data
 *
 * @return array<mixed>
 */
function arrayField(array $data, string $key, bool $optional = false): array
{
    if ($optional && !\array_key_exists($key, $data)) {
        return [];
    }

    $value = field($data, $key);
    if (!\is_array($value)) {
        fail('"' . $key . '" in the SBOM is not an object or list.');
    }

    return $value;
}

/**
 * @param array<mixed> $data
 */
function stringField(array $data, string $key): string
{
    $value = field($data, $key);
    if (!\is_string($value)) {
        fail('"' . $key . '" in the SBOM is not a string.');
    }

    return $value;
}

/**
 * @return array<string, mixed>
 */
function libraryComponent(string $commit, string $directory, string $name): array
{
    $origin = gitShow($commit, $directory . '/ORIGIN.md');
    $where = $directory . '/ORIGIN.md';
    $version = match1('/^- Version: (\S+)/m', $origin, 'Version in ' . $where);
    $purl = match1('/^- Package URL: `([^`]+)`/m', $origin, 'Package URL in ' . $where);
    $license = match1('/SPDX `([^`]+)`/', $origin, 'SPDX license in ' . $where);
    $sha256 = match1('/SHA-256 `([0-9a-f]{64})`/', $origin, 'SHA-256 in ' . $where);
    $project = match1('/^- Projekt \/ project: \[[^\]]+\]\((https:\/\/[^)]+)\)/m', $origin, 'project URL in ' . $where);
    $copyright = match1('/^- Copyright: (.+)$/m', $origin, 'copyright in ' . $where);
    $file = match1('/^- Datei \/ file: `([^`]+)`/m', $origin, 'file in ' . $where);

    $path = $directory . '/' . $file;
    if (hash('sha256', gitShow($commit, $path)) !== $sha256) {
        fail($path . ' does not match the SHA-256 in ' . $where . '.');
    }

    return [
        'bom-ref' => 'bundled:' . $name,
        'type' => 'library',
        'name' => $name,
        'version' => $version,
        'copyright' => $copyright,
        'licenses' => [['license' => ['id' => $license]]],
        'purl' => $purl,
        'hashes' => [['alg' => 'SHA-256', 'content' => $sha256]],
        'externalReferences' => [['type' => 'vcs', 'url' => $project]],
        'evidence' => ['occurrences' => [['location' => $path]]],
    ];
}

/**
 * @param array{bom-ref: string, type: string, name: string, description: string, copyright: string, license: string, files: list<string>, ancestors: list<array{name: string, version: string, purl: string}>, vcs: string} $fragment
 *
 * @return array<string, mixed>
 */
function fragmentComponent(array $fragment): array
{
    $ancestors = [];
    foreach ($fragment['ancestors'] as $ancestor) {
        $ancestors[] = ['type' => 'library'] + $ancestor;
    }

    return [
        'bom-ref' => $fragment['bom-ref'],
        'type' => $fragment['type'],
        'name' => $fragment['name'],
        'description' => $fragment['description'],
        'copyright' => $fragment['copyright'],
        'licenses' => [['license' => ['id' => $fragment['license']]]],
        'externalReferences' => [['type' => 'vcs', 'url' => $fragment['vcs']]],
        'pedigree' => ['ancestors' => $ancestors],
        'evidence' => ['occurrences' => array_map(
            static fn (string $file): array => ['location' => $file],
            $fragment['files'],
        )],
    ];
}

/**
 * First file of every row in THIRD-PARTY-NOTICES.md, section "Im Plugin enthalten".
 *
 * @return list<string>
 */
function noticedFiles(string $notices): array
{
    $start = strpos($notices, NOTICES_SECTION);
    if ($start === false) {
        fail('Section "' . NOTICES_SECTION . '" missing in THIRD-PARTY-NOTICES.md.');
    }
    $section = substr($notices, $start + \strlen(NOTICES_SECTION));
    $end = strpos($section, "\n## ");
    if ($end !== false) {
        $section = substr($section, 0, $end);
    }

    $files = [];
    foreach (explode("\n", $section) as $line) {
        if (!str_starts_with($line, '| ') || str_starts_with($line, '| Komponente') || str_starts_with($line, '|---')) {
            continue;
        }
        $cells = explode(' | ', trim($line, '| '));
        $files[] = match1('/`([^`]+)`/', (string) end($cells), 'file column in THIRD-PARTY-NOTICES.md');
    }
    if ($files === []) {
        fail('No entries found in section "' . NOTICES_SECTION . '" of THIRD-PARTY-NOTICES.md.');
    }

    return $files;
}

$root = \dirname(__DIR__);
chdir($root);
require $root . '/vendor/autoload.php';

$revision = $argv[1] ?? 'HEAD';
$commit = trim(run(['git', 'rev-parse', '--verify', $revision . '^{commit}']));
$version = match1('#<version>([^<]+)</version>#', gitShow($commit, 'plugin.xml'), '<version> from plugin.xml');

// The Composer part is resolved from the working tree; it must describe the
// same runtime requirements as the revision.
$composerAtRevision = decodeObject(gitShow($commit, 'composer.json'), 'composer.json at ' . $revision);
$composerWorkingTree = decodeObject((string) file_get_contents('composer.json'), 'composer.json');
if (($composerAtRevision['require'] ?? null) !== ($composerWorkingTree['require'] ?? null)) {
    fail('The runtime requirements in composer.json differ between ' . $revision . ' and the working tree.');
}

$composerSbom = tempnam(sys_get_temp_dir(), 'sbom');
if ($composerSbom === false) {
    fail('Could not create a temporary file.');
}
$composerBinary = getenv('COMPOSER_BINARY');
run([
    \is_string($composerBinary) && $composerBinary !== '' ? $composerBinary : 'composer', 'CycloneDX:make-sbom', '--no-interaction',
    '--omit=dev', '--output-format=JSON', '--spec-version=1.6', '--output-reproducible', '--validate',
    '--mc-version=' . $version, '--output-file=' . $composerSbom,
]);
$bom = decodeObject((string) file_get_contents($composerSbom), 'Composer SBOM');
unlink($composerSbom);

$metadata = arrayField($bom, 'metadata');
$main = arrayField($metadata, 'component');
$composerRef = stringField($main, 'bom-ref');
// Composer names the main component after the working-tree branch.
$mainRef = 'hqgmbh/sw5-community-security-' . $version;
$main['bom-ref'] = $mainRef;

// Replace the working-tree commit reported by Composer with the revision.
$main['properties'] = array_values(array_filter(
    arrayField($main, 'properties', true),
    static fn ($property): bool => !\is_array($property) || !\in_array($property['name'] ?? null, ['cdx:composer:package:distReference', 'cdx:composer:package:sourceReference'], true),
));
$references = arrayField($main, 'externalReferences', true);
$references[] = ['type' => 'vcs', 'url' => REPOSITORY_URL . '/tree/' . $commit, 'comment' => 'source revision of this release'];
$main['externalReferences'] = $references;
$metadata['component'] = $main;
$bom['metadata'] = $metadata;

$bundled = [];
foreach (explode("\n", trim(run(['git', 'ls-tree', '-r', '--name-only', $commit, 'Resources/views/backend']))) as $path) {
    if (preg_match(LIBRARY_PATTERN, $path, $match) === 1) {
        $bundled[] = libraryComponent($commit, \dirname($path), $match[1]);
    }
}
foreach (BUNDLED_FRAGMENTS as $fragment) {
    $bundled[] = fragmentComponent($fragment);
}

$covered = [];
foreach ($bundled as $component) {
    foreach (arrayField(arrayField($component, 'evidence'), 'occurrences') as $occurrence) {
        $covered[] = stringField(\is_array($occurrence) ? $occurrence : [], 'location');
    }
}
foreach (noticedFiles(gitShow($commit, 'THIRD-PARTY-NOTICES.md')) as $file) {
    if (!\in_array($file, $covered, true)) {
        fail('THIRD-PARTY-NOTICES.md lists ' . $file . ', but the SBOM has no component for it.');
    }
}

$components = arrayField($bom, 'components', true);
$dependencies = [];
foreach (arrayField($bom, 'dependencies', true) as $dependency) {
    if (!\is_array($dependency)) {
        fail('A dependency in the SBOM is not an object.');
    }
    $dependencyRef = stringField($dependency, 'ref');
    $dependency['ref'] = $dependencyRef === $composerRef ? $mainRef : $dependencyRef;
    $dependencies[] = $dependency;
}
foreach ($bundled as $component) {
    $components[] = $component;
    $ref = stringField($component, 'bom-ref');
    $dependencies[] = ['ref' => $ref];
    foreach ($dependencies as $index => $dependency) {
        if (($dependency['ref'] ?? null) === $mainRef) {
            $dependencies[$index]['dependsOn'] = array_merge(arrayField($dependency, 'dependsOn', true), [$ref]);
        }
    }
}
$bom['components'] = $components;
$bom['dependencies'] = $dependencies;

$json = json_encode($bom, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n";
$error = (new JsonStrictValidator(Version::v1dot6))->validateString($json);
if ($error !== null) {
    fail('The SBOM is not valid CycloneDX 1.6: ' . $error->getMessage());
}

if (!is_dir('build') && !mkdir('build') && !is_dir('build')) {
    fail('Could not create build/.');
}
$output = 'build/' . PLUGIN_NAME . '-' . $version . '.cdx.json';
file_put_contents($output, $json);
echo $output, \PHP_EOL;
