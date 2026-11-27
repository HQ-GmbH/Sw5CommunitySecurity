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

namespace Sw5CommunitySecurity\Components\TemplateGuard;

use Doctrine\DBAL\Connection;
use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;

/**
 * Inspects content already stored in the database that Shopware renders with
 * Smarty. Read-only: nothing is changed. Results name the source, the record
 * and the rule, never the template content.
 */
final class StoredTemplateScanner
{
    /**
     * Snippet key => English default of the source and record labels.
     */
    public const LABELS = [
        'scan_source_mail_template' => 'Email template',
        'scan_source_product_export' => 'Product export',
        'scan_source_newsletter' => 'Newsletter',
        'scan_source_newsletter_container' => 'Newsletter container',
        'scan_source_newsletter_text' => 'Newsletter text',
        'scan_source_newsletter_link' => 'Newsletter link',
        'scan_source_document_box' => 'Document element',
        'scan_source_payment_method' => 'Payment method',
        'scan_source_country' => 'Country',
        'scan_source_snippet' => 'Snippet',
        'scan_source_emotion_html' => 'Shopping world (HTML code)',
        'scan_source_content_type' => 'Content type',
        'scan_source_config_default' => 'Basic setting (default value)',
        'scan_source_config' => 'Basic setting',
        'scan_source_translation' => 'Translation',
        'scan_record_config' => '[name] / shop [shop]',
        'scan_record_translation' => '[type] / key [key] / language [language]',
    ];

    /**
     * @readonly
     */
    private RowReader $connection;

    /**
     * @readonly
     */
    private TemplateGuard $guard;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(RowReader $connection, TemplateGuard $guard, MessageFormatter $messages)
    {
        $this->connection = $connection;
        $this->guard = $guard;
        $this->messages = $messages;
    }

    /**
     * @return list<array{source: string, record: string, field: string, rules: list<string>}>
     */
    public function scan(bool $strict = true): array
    {
        $results = [];

        $tables = [
            // source label snippet key => [SQL, record label columns, template columns]
            'scan_source_mail_template' => ['SELECT id, name, frommail, fromname, subject, content, contentHTML FROM s_core_config_mails', ['id', 'name'], ['frommail', 'fromname', 'subject', 'content', 'contentHTML']],
            'scan_source_product_export' => ['SELECT id, name, header, body, footer FROM s_export', ['id', 'name'], ['header', 'body', 'footer']],
            'scan_source_newsletter' => ['SELECT id, subject, sendermail, sendername FROM s_campaigns_mailings', ['id'], ['subject', 'sendermail', 'sendername']],
            'scan_source_newsletter_container' => ['SELECT id, promotionID, description FROM s_campaigns_containers', ['id', 'promotionID'], ['description']],
            'scan_source_newsletter_text' => ['SELECT id, parentID, html FROM s_campaigns_html', ['id', 'parentID'], ['html']],
            'scan_source_newsletter_link' => ['SELECT id, parentID, description FROM s_campaigns_links', ['id', 'parentID'], ['description']],
            'scan_source_document_box' => ['SELECT id, documentID, name, value FROM s_core_documents_box', ['documentID', 'name'], ['value']],
            'scan_source_payment_method' => ['SELECT id, name, additionaldescription FROM s_core_paymentmeans', ['id', 'name'], ['additionaldescription']],
            'scan_source_country' => ['SELECT id, countryiso, notice FROM s_core_countries', ['id', 'countryiso'], ['notice']],
            'scan_source_snippet' => ['SELECT id, namespace, name, shopID, localeID, value FROM s_core_snippets', ['namespace', 'name', 'shopID', 'localeID'], ['value']],
            'scan_source_emotion_html' => [
                'SELECT v.id, v.emotionID, v.value FROM s_emotion_element_value v'
                . ' INNER JOIN s_library_component_field f ON f.id = v.fieldID WHERE f.name = \'smarty\'',
                ['emotionID', 'id'],
                ['value'],
            ],
        ];

        foreach ($tables as $sourceKey => [$sql, $recordColumns, $templateColumns]) {
            $source = $this->label($sourceKey);
            foreach ($this->connection->fetchAll($sql) as $row) {
                $fields = array_intersect_key($row, array_flip($templateColumns));
                $this->add($results, $source, $this->record($row, $recordColumns), $fields, $strict);
            }
        }

        $this->scanConfig($results, $strict);
        $this->scanTranslations($results, $strict);
        $this->scanContentTypes($results, $strict);

        return $results;
    }

    /**
     * Content types (Shopware 5.6+): the SEO URL template is compiled as
     * Smarty (Core/sRewriteTable.php, createContentTypeUrls()); the backend
     * stores it as JSON in s_content_types.config with plain DBAL.
     *
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanContentTypes(array &$results, bool $strict): void
    {
        $source = $this->label('scan_source_content_type');
        foreach ($this->connection->fetchAll('SELECT internalName, config FROM s_content_types') as $row) {
            $config = \is_string($row['config'] ?? null) ? json_decode($row['config'], true) : null;
            $template = \is_array($config) ? ($config['seoUrlTemplate'] ?? null) : null;
            $this->add($results, $source, $this->column($row, 'internalName'), ['seoUrlTemplate' => $template], $strict);
        }
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanConfig(array &$results, bool $strict): void
    {
        $names = GuardedSources::CONFIG_ELEMENTS;

        $defaults = $this->connection->fetchAll(
            'SELECT name, value FROM s_core_config_elements WHERE LOWER(name) IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY],
        );
        $source = $this->label('scan_source_config_default');
        foreach ($defaults as $row) {
            $this->add($results, $source, $this->column($row, 'name'), ['value' => $this->unserialize($row['value'] ?? null)], $strict);
        }

        $values = $this->connection->fetchAll(
            'SELECT e.name, v.shop_id, v.value FROM s_core_config_values v'
            . ' INNER JOIN s_core_config_elements e ON e.id = v.element_id WHERE LOWER(e.name) IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY],
        );
        $source = $this->label('scan_source_config');
        foreach ($values as $row) {
            $record = $this->messages->formatText('scan_record_config', self::LABELS['scan_record_config'], [
                'name' => $this->column($row, 'name'),
                'shop' => $this->column($row, 'shop_id'),
            ]);
            $this->add($results, $source, $record, ['value' => $this->unserialize($row['value'] ?? null)], $strict);
        }
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanTranslations(array &$results, bool $strict): void
    {
        $rows = $this->connection->fetchAll(
            'SELECT objecttype, objectkey, objectlanguage, objectdata FROM s_core_translations WHERE objecttype IN (:types)',
            ['types' => GuardedSources::TRANSLATION_TYPES],
            ['types' => Connection::PARAM_STR_ARRAY],
        );

        $source = $this->label('scan_source_translation');
        foreach ($rows as $row) {
            $data = $this->unserialize($row['objectdata'] ?? null);
            $record = $this->messages->formatText('scan_record_translation', self::LABELS['scan_record_translation'], [
                'type' => $this->column($row, 'objecttype'),
                'key' => $this->column($row, 'objectkey'),
                'language' => $this->column($row, 'objectlanguage'),
            ]);
            $this->add($results, $source, $record, \is_array($data) ? $data : ['objectdata' => $data], $strict);
        }
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     * @param array<array-key, mixed>                                                         $fields
     */
    private function add(array &$results, string $source, string $record, array $fields, bool $strict): void
    {
        foreach ($this->guard->findViolations($fields, $strict) as $field => $findings) {
            $results[] = [
                'source' => $source,
                'record' => $record,
                // Numeric field paths come back as integer keys.
                'field' => (string) $field,
                'rules' => array_values(array_unique(array_map(static fn (Finding $finding): string => $finding->rule, $findings))),
            ];
        }
    }

    /**
     * Translated source label; the result is plain text for the console.
     *
     * @param key-of<self::LABELS> $key
     */
    private function label(string $key): string
    {
        return $this->messages->formatText($key, self::LABELS[$key]);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $columns
     */
    private function record(array $row, array $columns): string
    {
        return implode(' / ', array_map(fn (string $column): string => $this->column($row, $column), $columns));
    }

    /**
     * Returns a database column as string; DBAL returns scalars or null.
     *
     * @param array<string, mixed> $row
     */
    private function column(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Shopware stores these values with PHP serialize(). Objects are never
     * instantiated; values that cannot be decoded are inspected as stored.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function unserialize($value)
    {
        if (!\is_string($value) || $value === '') {
            return $value;
        }

        if ($value === 'b:0;') {
            return false;
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);

        return $decoded === false ? $value : $decoded;
    }
}
