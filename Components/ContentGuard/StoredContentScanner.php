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

namespace Sw5CommunitySecurity\Components\ContentGuard;

use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;

/**
 * Read-only scan of stored editor content (HTML) for script, so existing
 * content can be reviewed. Nothing is changed. Results name source, record,
 * field and rule, never the content. Rows are read in pages of PAGE_SIZE by
 * id, and only rows containing "<" are inspected, so large catalogues stay
 * within memory.
 */
final class StoredContentScanner
{
    public const PAGE_SIZE = 500;

    /**
     * Snippet key => English default of the source and record labels.
     */
    public const LABELS = [
        'scan_source_shop_page' => 'Shop page',
        'scan_source_product' => 'Product',
        'scan_source_category' => 'Category',
        'scan_source_manufacturer' => 'Manufacturer',
        'scan_source_blog' => 'Blog',
        'scan_source_form' => 'Form',
        'scan_source_review_answer' => 'Review (answer)',
        'scan_source_newsletter_text' => 'Newsletter text',
        'scan_source_emotion_text' => 'Shopping world (text)',
        'scan_source_attribute' => 'Free text field [table]',
        'scan_source_translation' => 'Translation',
        'scan_record_translation' => '[type] / key [key] / language [language]',
    ];

    /**
     * Source label snippet key => [table, record label columns, content columns].
     */
    private const TABLES = [
        'scan_source_shop_page' => ['s_cms_static', ['id', 'description'], ['html']],
        'scan_source_product' => ['s_articles', ['id', 'name'], ['description_long']],
        'scan_source_category' => ['s_categories', ['id', 'description'], ['cmstext']],
        'scan_source_manufacturer' => ['s_articles_supplier', ['id', 'name'], ['description']],
        'scan_source_blog' => ['s_blog', ['id', 'title'], ['description']],
        'scan_source_form' => ['s_cms_support', ['id', 'name'], ['text', 'text2']],
        'scan_source_review_answer' => ['s_articles_vote', ['id', 'articleID'], ['answer']],
        'scan_source_newsletter_text' => ['s_campaigns_html', ['id', 'parentID'], ['html']],
    ];

    private const IDENTIFIER = '/^[A-Za-z0-9_]+$/';

    /**
     * @readonly
     */
    private RowReader $rows;

    /**
     * @readonly
     */
    private ContentGuard $guard;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(RowReader $rows, ContentGuard $guard, MessageFormatter $messages)
    {
        $this->rows = $rows;
        $this->guard = $guard;
        $this->messages = $messages;
    }

    /**
     * @return list<array{source: string, record: string, field: string, rules: list<string>}>
     */
    public function scan(): array
    {
        $results = [];

        foreach (self::TABLES as $sourceKey => [$table, $recordColumns, $contentColumns]) {
            $this->scanTable($results, $this->label($sourceKey), $table, $recordColumns, $contentColumns, '');
        }

        $this->scanTable(
            $results,
            $this->label('scan_source_emotion_text'),
            's_emotion_element_value',
            ['emotionID', 'id'],
            ['value'],
            " AND fieldID IN (SELECT id FROM s_library_component_field WHERE x_type = 'tinymce')",
        );

        foreach ($this->rows->fetchAll("SELECT table_name, column_name FROM s_attribute_configuration WHERE column_type = 'html' ORDER BY table_name, column_name") as $row) {
            $table = $row['table_name'] ?? null;
            $column = $row['column_name'] ?? null;
            if (\is_string($table) && \is_string($column) && preg_match(self::IDENTIFIER, $table) === 1 && preg_match(self::IDENTIFIER, $column) === 1) {
                $source = $this->messages->formatText('scan_source_attribute', self::LABELS['scan_source_attribute'], ['table' => $table]);
                $this->scanTable($results, $source, $table, ['id'], [$column], '');
            }
        }

        $this->scanTranslations($results);

        return $results;
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     * @param list<string>                                                                    $recordColumns
     * @param list<string>                                                                    $contentColumns
     */
    private function scanTable(array &$results, string $source, string $table, array $recordColumns, array $contentColumns, string $condition): void
    {
        $columns = array_values(array_unique(array_merge(['id'], $recordColumns, $contentColumns)));
        $markup = implode(' OR ', array_map(static fn (string $column): string => $column . " LIKE '%<%'", $contentColumns));
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $table
            . ' WHERE id > ? AND (' . $markup . ')' . $condition
            . ' ORDER BY id LIMIT ' . self::PAGE_SIZE;

        $lastId = 0;
        do {
            $before = $lastId;
            $page = $this->rows->fetchAll($sql, [$lastId]);
            foreach ($page as $row) {
                $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
                $this->add($results, $source, $this->record($row, $recordColumns), array_intersect_key($row, array_flip($contentColumns)));
            }
        } while (\count($page) === self::PAGE_SIZE && $lastId > $before);
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanTranslations(array &$results): void
    {
        $sql = "SELECT id, objecttype, objectkey, objectlanguage, objectdata FROM s_core_translations WHERE id > ? AND objectdata LIKE '%<%' ORDER BY id LIMIT " . self::PAGE_SIZE;

        $source = $this->label('scan_source_translation');
        $lastId = 0;
        do {
            $before = $lastId;
            $page = $this->rows->fetchAll($sql, [$lastId]);
            foreach ($page as $row) {
                $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
                $data = $this->unserialize($row['objectdata'] ?? null);
                $record = $this->messages->formatText('scan_record_translation', self::LABELS['scan_record_translation'], [
                    'type' => $this->column($row, 'objecttype'),
                    'key' => $this->column($row, 'objectkey'),
                    'language' => $this->column($row, 'objectlanguage'),
                ]);
                $this->add($results, $source, $record, \is_array($data) ? $data : ['objectdata' => $data]);
            }
        } while (\count($page) === self::PAGE_SIZE && $lastId > $before);
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     * @param array<array-key, mixed>                                                         $fields
     */
    private function add(array &$results, string $source, string $record, array $fields): void
    {
        foreach ($this->guard->findViolations($fields) as $field => $rules) {
            // Numeric field paths come back as integer keys.
            $results[] = ['source' => $source, 'record' => $record, 'field' => (string) $field, 'rules' => $rules];
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
     * @param array<string, mixed> $row
     */
    private function column(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Serialized values are read without creating objects.
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

        $decoded = @unserialize($value, ['allowed_classes' => false]);

        return $decoded === false && $value !== 'b:0;' ? $value : $decoded;
    }
}
