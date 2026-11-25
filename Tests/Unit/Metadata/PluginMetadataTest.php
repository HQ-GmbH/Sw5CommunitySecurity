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

namespace Sw5CommunitySecurity\Tests\Unit\Metadata;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\PasswordPolicy\PasswordPolicy;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheck;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheckRunner;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLabels;

/**
 * Checks the requirements of the Shopware 5 devdocs for plugin metadata
 * (plugin-quick-start, plugin-guidelines): German and English texts for
 * every label, description and changelog entry, a version_compare
 * compatible version and complete snippet translations.
 */
final class PluginMetadataTest extends TestCase
{
    private const LANGUAGES = ['de', 'en'];

    private const SNIPPET_LOCALES = ['de_DE', 'en_GB'];

    private const SNIPPET_FILE = __DIR__ . '/../../../Resources/snippets/backend/sw5_community_security/messages.ini';

    /**
     * Snippet keys used by the plugin code; the keys of the security check
     * are taken from SecurityCheck and SecurityCheckRunner.
     */
    private const SNIPPET_KEYS = [
        'template_rejected', 'rule_tag', 'rule_function_name', 'rule_block_name', 'rule_include_file',
        'rule_math_equation', 'rule_static_class', 'rule_too_large', 'rule_too_deep', 'upload_rejected',
        'two_factor_login_field',
    ];

    public function testPluginXmlHasRequiredFields(): void
    {
        $xpath = $this->load('plugin.xml');

        foreach (['version', 'copyright', 'license', 'author', 'link'] as $field) {
            static::assertNotSame('', trim($this->text($xpath, '/plugin/' . $field)), $field . ' must not be empty');
        }

        $version = trim($this->text($xpath, '/plugin/version'));
        static::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $changelog = $xpath->query('/plugin/changelog[@version="' . $version . '"]');
        static::assertNotFalse($changelog);
        static::assertSame(1, $changelog->length, 'changelog for the current version');
    }

    public function testPluginXmlTextsExistInGermanAndEnglish(): void
    {
        $xpath = $this->load('plugin.xml');

        $this->assertLanguages($xpath, '/plugin/label', 'plugin label');
        $this->assertLanguages($xpath, '/plugin/description', 'plugin description');

        $changelogs = $xpath->query('/plugin/changelog');
        static::assertNotFalse($changelogs);
        static::assertGreaterThan(0, $changelogs->length);
        foreach ($changelogs as $changelog) {
            static::assertInstanceOf(DOMElement::class, $changelog);
            $this->assertLanguages($xpath, 'changes', 'changelog ' . $changelog->getAttribute('version'), $changelog);
        }
    }

    public function testEveryConfigElementIsTranslated(): void
    {
        $xpath = $this->load('Resources/config.xml');

        $elements = $xpath->query('/config/elements/element');
        static::assertNotFalse($elements);
        static::assertGreaterThan(0, $elements->length);
        foreach ($elements as $element) {
            static::assertInstanceOf(DOMElement::class, $element);
            $name = $this->text($xpath, 'name', $element);
            $this->assertLanguages($xpath, 'label', $name . ' label', $element);
            $this->assertLanguages($xpath, 'description', $name . ' description', $element);

            $options = $xpath->query('store/option', $element);
            static::assertNotFalse($options);
            foreach ($options as $option) {
                static::assertInstanceOf(DOMElement::class, $option);
                $this->assertLanguages($xpath, 'label', $name . ' option', $option);
            }
        }
    }

    /**
     * Defaults that PluginConfig uses when no value is stored must match the
     * defaults Shopware shows in the configuration form.
     */
    public function testConfigDefaultsMatchPluginConfig(): void
    {
        $xpath = $this->load('Resources/config.xml');
        $defaults = [
            'headerCspMode' => PluginConfig::OFF,
            'headerCspPolicy' => PluginConfig::DEFAULT_CSP_FRONTEND,
            'headerCspBackendPolicy' => PluginConfig::DEFAULT_CSP_BACKEND,
            'headerCspReportUri' => '',
            'headerPermissionsPolicy' => PluginConfig::DEFAULT_PERMISSIONS_POLICY,
            'customerSessionCookieSameSite' => PluginConfig::OFF,
            'backendPasswordMinLength' => (string) PasswordPolicy::DEFAULT_MIN_LENGTH,
        ];

        foreach ($defaults as $name => $expected) {
            static::assertSame($expected, $this->text($xpath, '/config/elements/element[name="' . $name . '"]/value'), $name);
        }
    }

    public function testSnippetsAreCompleteInAllLocales(): void
    {
        $snippets = parse_ini_file(self::SNIPPET_FILE, true, \INI_SCANNER_RAW);
        static::assertIsArray($snippets);
        $english = $snippets['en_GB'] ?? null;
        static::assertIsArray($english);

        foreach (self::SNIPPET_LOCALES as $locale) {
            $texts = $snippets[$locale] ?? null;
            static::assertIsArray($texts, $locale);
            static::assertSame(array_keys($english), array_keys($texts), $locale . ': same keys and order as en_GB');
            $required = array_merge(
                self::SNIPPET_KEYS,
                array_keys(SecurityCheckRunner::LABELS),
                array_keys(TwoFactorLabels::LABELS),
                ['password_rejected'],
                array_map(static fn (string $rule): string => 'password_rule_' . $rule, array_keys(PasswordPolicy::MESSAGES_BY_RULE)),
                array_map(static fn (string $id): string => 'check_' . $id, array_keys(SecurityCheck::TITLES)),
            );
            static::assertSame([], array_values(array_diff($required, array_keys($texts))), $locale . ': missing keys');
            foreach ($texts as $key => $text) {
                static::assertIsString($text);
                static::assertNotSame('', trim($text), $locale . '.' . $key);
            }
        }
    }

    /**
     * The English snippets must match the defaults in the code, which are
     * used when a snippet is missing.
     */
    public function testEnglishSnippetsMatchCodeDefaults(): void
    {
        $snippets = parse_ini_file(self::SNIPPET_FILE, true, \INI_SCANNER_RAW);
        static::assertIsArray($snippets);
        $english = $snippets['en_GB'] ?? null;
        static::assertIsArray($english);

        $defaults = TwoFactorLabels::LABELS + ['password_rejected' => PasswordPolicy::REJECTED_MESSAGE];
        foreach (PasswordPolicy::MESSAGES_BY_RULE as $rule => $message) {
            $defaults['password_rule_' . $rule] = $message;
        }

        foreach ($defaults as $key => $default) {
            static::assertSame($default, $english[$key] ?? null, $key);
        }
    }

    public function testSnippetsContainNoSmartySyntax(): void
    {
        // Shopware compiles snippet values as Smarty when they are used in templates.
        $content = file_get_contents(self::SNIPPET_FILE);
        static::assertIsString($content);
        static::assertStringNotContainsString('{', $content);
        static::assertStringNotContainsString('}', $content);
    }

    private function load(string $file): DOMXPath
    {
        $document = new DOMDocument();
        static::assertTrue($document->load(__DIR__ . '/../../../' . $file), $file);

        return new DOMXPath($document);
    }

    private function text(DOMXPath $xpath, string $query, ?DOMElement $context = null): string
    {
        $nodes = $xpath->query($query, $context);
        static::assertNotFalse($nodes);
        $node = $nodes->item(0);
        static::assertInstanceOf(DOMElement::class, $node, $query);

        return $node->textContent;
    }

    private function assertLanguages(DOMXPath $xpath, string $query, string $label, ?DOMElement $context = null): void
    {
        $nodes = $xpath->query($query, $context);
        static::assertNotFalse($nodes);

        $languages = [];
        foreach ($nodes as $node) {
            static::assertInstanceOf(DOMElement::class, $node);
            static::assertNotSame('', trim($node->textContent), $label . ' must not be empty');
            $languages[] = $node->getAttribute('lang');
        }

        sort($languages);
        static::assertSame(self::LANGUAGES, $languages, $label . ': exactly one German and one English text');
    }
}
