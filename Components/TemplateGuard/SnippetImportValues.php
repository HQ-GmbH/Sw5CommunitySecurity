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

/**
 * Values of a snippet import file as Shopware stores them.
 *
 * Backend_Snippet::importSnippetAction() reads the file (CSV with ";" and
 * '"'; Shopware 5.6 also XML), removes a leading apostrophe, trims the value
 * and decodes HTML entities before the plain SQL insert
 * (engine/Shopware/Controllers/Backend/Snippet.php, getFormatSnippetForSave(),
 * 5.6.10 and 5.7.20). The guard therefore inspects every cell separately and
 * in this decoded form, plus the decoded file as a whole as a fallback for
 * files the CSV reader splits differently.
 */
final class SnippetImportValues
{
    /**
     * @return array<string, string> field label => value as stored
     */
    public static function fromFile(string $content, string $fileName): array
    {
        $values = ['file' => self::formatForSave($content)];
        $cells = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION)) === 'xml' ? self::xmlCells($content) : self::csvCells($content);
        foreach ($cells as $label => $cell) {
            $values[$label] = self::formatForSave(self::stripApostrophe($cell));
        }

        return $values;
    }

    /**
     * Same result as Shopware's getFormatSnippetForSave(): text in the
     * detected encoding, converted to UTF-8, HTML entities decoded
     * (ENT_NOQUOTES).
     */
    public static function formatForSave(string $value): string
    {
        $encoding = mb_detect_encoding($value, ['utf-8', 'iso-8859-1', 'iso-8859-15', 'windows-1251']);
        $utf8 = mb_convert_encoding($value, 'UTF-8', \is_string($encoding) ? $encoding : 'utf-8');

        return html_entity_decode(\is_string($utf8) ? $utf8 : $value, \ENT_NOQUOTES, 'UTF-8');
    }

    private static function stripApostrophe(string $value): string
    {
        return trim(preg_replace("/^'/", '', $value) ?? $value);
    }

    /**
     * @return array<string, string>
     */
    private static function csvCells(string $content): array
    {
        $handle = fopen('php://memory', 'r+b');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);
        $cells = [];
        $row = 0;
        while (\is_array($fields = fgetcsv($handle, 0, ';', '"', ''))) {
            ++$row;
            foreach ($fields as $column => $field) {
                if (\is_string($field) && $field !== '') {
                    $cells['row ' . $row . ', column ' . ($column + 1)] = $field;
                }
            }
        }
        fclose($handle);

        return $cells;
    }

    /**
     * @return array<string, string>
     */
    private static function xmlCells(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', \LIBXML_NOCDATA | \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            return [];
        }

        $cells = [];
        $index = 0;
        $nodes = $xml->xpath('//*|//@*');
        foreach (\is_array($nodes) ? $nodes : [] as $node) {
            $text = (string) $node;
            if (trim($text) !== '') {
                $cells['xml value ' . ++$index] = $text;
            }
        }

        return $cells;
    }
}
