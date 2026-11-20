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

/**
 * Inspects content already stored in the database that Shopware renders with
 * Smarty. Read-only: nothing is changed. Results name the source, the record
 * and the rule, never the template content.
 */
final class StoredTemplateScanner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TemplateGuard $guard,
    ) {
    }

    /**
     * @return list<array{source: string, record: string, field: string, rules: list<string>}>
     */
    public function scan(bool $strict = true): array
    {
        $results = [];

        $tables = [
            // source label => [SQL, record label columns, template columns]
            'E-Mail-Vorlage' => ['SELECT id, name, frommail, fromname, subject, content, contentHTML FROM s_core_config_mails', ['id', 'name'], ['frommail', 'fromname', 'subject', 'content', 'contentHTML']],
            'Produktexport' => ['SELECT id, name, header, body, footer FROM s_export', ['id', 'name'], ['header', 'body', 'footer']],
            'Newsletter' => ['SELECT id, subject, sendermail, sendername FROM s_campaigns_mailings', ['id'], ['subject', 'sendermail', 'sendername']],
            'Newsletter-Container' => ['SELECT id, promotionID, description FROM s_campaigns_containers', ['id', 'promotionID'], ['description']],
            'Newsletter-Text' => ['SELECT id, parentID, html FROM s_campaigns_html', ['id', 'parentID'], ['html']],
            'Newsletter-Link' => ['SELECT id, parentID, description FROM s_campaigns_links', ['id', 'parentID'], ['description']],
            'Beleg-Baustein' => ['SELECT id, documentID, name, value FROM s_core_documents_box', ['documentID', 'name'], ['value']],
            'Zahlungsart' => ['SELECT id, name, additionaldescription FROM s_core_paymentmeans', ['id', 'name'], ['additionaldescription']],
            'Land' => ['SELECT id, countryiso, notice FROM s_core_countries', ['id', 'countryiso'], ['notice']],
            'Einkaufswelt (HTML-Code)' => [
                'SELECT v.id, v.emotionID, v.value FROM s_emotion_element_value v'
                . ' INNER JOIN s_library_component_field f ON f.id = v.fieldID WHERE f.name = \'smarty\'',
                ['emotionID', 'id'],
                ['value'],
            ],
        ];

        foreach ($tables as $source => [$sql, $recordColumns, $templateColumns]) {
            foreach ($this->connection->fetchAllAssociative($sql) as $row) {
                $fields = array_intersect_key($row, array_flip($templateColumns));
                $this->add($results, $source, $this->record($row, $recordColumns), $fields, $strict);
            }
        }

        $this->scanConfig($results, $strict);
        $this->scanTranslations($results, $strict);

        return $results;
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanConfig(array &$results, bool $strict): void
    {
        $names = GuardedSources::CONFIG_ELEMENTS;

        $defaults = $this->connection->fetchAllAssociative(
            'SELECT name, value FROM s_core_config_elements WHERE LOWER(name) IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );
        foreach ($defaults as $row) {
            $this->add($results, 'Grundeinstellung (Standardwert)', (string) $row['name'], ['value' => $this->unserialize($row['value'])], $strict);
        }

        $values = $this->connection->fetchAllAssociative(
            'SELECT e.name, v.shop_id, v.value FROM s_core_config_values v'
            . ' INNER JOIN s_core_config_elements e ON e.id = v.element_id WHERE LOWER(e.name) IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );
        foreach ($values as $row) {
            $this->add($results, 'Grundeinstellung', $row['name'] . ' / Shop ' . $row['shop_id'], ['value' => $this->unserialize($row['value'])], $strict);
        }
    }

    /**
     * @param list<array{source: string, record: string, field: string, rules: list<string>}> $results
     */
    private function scanTranslations(array &$results, bool $strict): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT objecttype, objectkey, objectlanguage, objectdata FROM s_core_translations WHERE objecttype IN (:types)',
            ['types' => GuardedSources::TRANSLATION_TYPES],
            ['types' => Connection::PARAM_STR_ARRAY]
        );

        foreach ($rows as $row) {
            $data = $this->unserialize($row['objectdata']);
            $record = sprintf('%s / Schlüssel %s / Sprache %s', $row['objecttype'], $row['objectkey'], $row['objectlanguage']);
            $this->add($results, 'Übersetzung', $record, \is_array($data) ? $data : ['objectdata' => $data], $strict);
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
                'field' => $field,
                'rules' => array_values(array_unique(array_map(static fn (Finding $finding): string => $finding->rule, $findings))),
            ];
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $columns
     */
    private function record(array $row, array $columns): string
    {
        return implode(' / ', array_map(static fn (string $column): string => (string) ($row[$column] ?? ''), $columns));
    }

    /**
     * Shopware stores these values with PHP serialize(). Objects are never
     * instantiated; values that cannot be decoded are inspected as stored.
     */
    private function unserialize(mixed $value): mixed
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
