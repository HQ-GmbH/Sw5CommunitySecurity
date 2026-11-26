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
     * Source label => [table, record label columns, content columns].
     */
    private const TABLES = [
        'Shopseite' => ['s_cms_static', ['id', 'description'], ['html']],
        'Artikel' => ['s_articles', ['id', 'name'], ['description_long']],
        'Kategorie' => ['s_categories', ['id', 'description'], ['cmstext']],
        'Hersteller' => ['s_articles_supplier', ['id', 'name'], ['description']],
        'Blog' => ['s_blog', ['id', 'title'], ['description']],
        'Formular' => ['s_cms_support', ['id', 'name'], ['text', 'text2']],
        'Bewertung (Antwort)' => ['s_articles_vote', ['id', 'articleID'], ['answer']],
        'Newsletter-Text' => ['s_campaigns_html', ['id', 'parentID'], ['html']],
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

    public function __construct(RowReader $rows, ContentGuard $guard)
    {
        $this->rows = $rows;
        $this->guard = $guard;
    }

    /**
     * @return list<array{source: string, record: string, field: string, rules: list<string>}>
     */
    public function scan(): array
    {
        $results = [];

        foreach (self::TABLES as $source => [$table, $recordColumns, $contentColumns]) {
            $this->scanTable($results, $source, $table, $recordColumns, $contentColumns, '');
        }

        $this->scanTable(
            $results,
            'Einkaufswelt (Text)',
            's_emotion_element_value',
            ['emotionID', 'id'],
            ['value'],
            " AND fieldID IN (SELECT id FROM s_library_component_field WHERE x_type = 'tinymce')",
        );

        foreach ($this->rows->fetchAll("SELECT table_name, column_name FROM s_attribute_configuration WHERE column_type = 'html' ORDER BY table_name, column_name") as $row) {
            $table = $row['table_name'] ?? null;
            $column = $row['column_name'] ?? null;
            if (\is_string($table) && \is_string($column) && preg_match(self::IDENTIFIER, $table) === 1 && preg_match(self::IDENTIFIER, $column) === 1) {
                $this->scanTable($results, 'Freitextfeld ' . $table, $table, ['id'], [$column], '');
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

        $lastId = 0;
        do {
            $before = $lastId;
            $page = $this->rows->fetchAll($sql, [$lastId]);
            foreach ($page as $row) {
                $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
                $data = $this->unserialize($row['objectdata'] ?? null);
                $record = \sprintf(
                    '%s / Schlüssel %s / Sprache %s',
                    $this->column($row, 'objecttype'),
                    $this->column($row, 'objectkey'),
                    $this->column($row, 'objectlanguage'),
                );
                $this->add($results, 'Übersetzung', $record, \is_array($data) ? $data : ['objectdata' => $data]);
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
            $results[] = ['source' => $source, 'record' => $record, 'field' => $field, 'rules' => $rules];
        }
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
