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

use Sw5CommunitySecurity\Components\Messages\DefaultMessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;

/**
 * Checks a set of template fields and builds the error message for the
 * backend. Free of Shopware dependencies to keep it unit-testable.
 */
final class TemplateGuard
{
    /**
     * Nested request data deeper than this is not inspected further and counts as a violation.
     */
    public const MAX_DEPTH = 8;
    /**
     * Snippet key, built-in default and "[tag]" value per rule. Snippet texts
     * contain no Smarty syntax; the technical tag is inserted as placeholder.
     */
    private const RULE_LABELS = [
        Finding::RULE_EXTENDS => ['rule_tag', '[tag]', '{extends}'],
        Finding::RULE_FETCH => ['rule_tag', '[tag]', '{fetch}'],
        Finding::RULE_PHP => ['rule_tag', '[tag]', '{php}'],
        Finding::RULE_FUNCTION_NAME => ['rule_function_name', '[tag] mit ungültigem Namen / with invalid name', '{function}'],
        Finding::RULE_BLOCK_NAME => ['rule_block_name', '[tag] mit ungültigem Namen / with invalid name', '{block}'],
        Finding::RULE_TEMPLATE_OBJECT => ['rule_tag', '[tag]', '$smarty.template_object'],
        Finding::RULE_INCLUDE_FILE => ['rule_include_file', '[tag] mit nicht erlaubtem Dateinamen / with disallowed file name', '{include}'],
        Finding::RULE_MATH_EQUATION => ['rule_math_equation', '[tag] mit nicht erlaubter Formel / with disallowed equation', '{math}'],
        Finding::RULE_STATIC_CLASS => ['rule_static_class', 'Zugriff auf statische Klassen ([tag]) / static class access ([tag])', '::'],
        Finding::RULE_TOO_LARGE => ['rule_too_large', 'Datei zu groß für die Prüfung / file too large to inspect', ''],
        Finding::RULE_TOO_DEEP => ['rule_too_deep', 'zu tief verschachtelte Daten / data nested too deeply', ''],
    ];

    private const MESSAGE_KEY = 'template_rejected';

    private const MESSAGE_DEFAULT = 'Community Security Guard: Die Vorlage enthält nicht erlaubte Smarty-Konstrukte und wurde nicht übernommen'
        . ' / The template contains disallowed Smarty constructs and was not accepted ([details]).';

    /**
     * @readonly
     */
    private TemplateInspector $inspector;

    /**
     * @readonly
     */
    private MessageFormatter $formatter;

    public function __construct(
        TemplateInspector $inspector,
        ?MessageFormatter $formatter = null
    ) {
        $this->inspector = $inspector;
        $this->formatter = $formatter ?? new MessageFormatter(new DefaultMessageCatalog());
    }

    /**
     * @param array<array-key, mixed> $fields field name => template; nested arrays are
     *                                        inspected recursively (field names become
     *                                        dotted paths), other non-string values are ignored
     * @param bool                    $strict also apply the {include}/{math} rules
     *
     * @return array<string, list<Finding>> field name => findings, only fields with findings
     */
    public function findViolations(array $fields, bool $strict = true): array
    {
        $violations = [];
        $this->collect($fields, '', 0, $strict, $violations);

        return $violations;
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @throws UnsafeTemplateException
     */
    public function assertSafe(array $fields, bool $strict = true): void
    {
        $violations = $this->findViolations($fields, $strict);

        if ($violations !== []) {
            throw new UnsafeTemplateException($this->buildMessage($violations));
        }
    }

    /**
     * @param array<string, list<Finding>> $violations
     */
    public function buildMessage(array $violations): string
    {
        $parts = [];
        foreach ($violations as $field => $findings) {
            $rules = array_values(array_unique(array_map(
                fn (Finding $finding): string => $this->ruleLabel($finding->rule),
                $findings,
            )));
            $parts[] = MessageFormatter::escape($field) . ': ' . implode(', ', $rules);
        }

        return $this->formatter->formatWithEscapedValues(self::MESSAGE_KEY, self::MESSAGE_DEFAULT, ['details' => implode('; ', $parts)]);
    }

    /**
     * Returns the escaped label of a rule.
     */
    private function ruleLabel(string $rule): string
    {
        if (!isset(self::RULE_LABELS[$rule])) {
            return MessageFormatter::escape($rule);
        }

        [$key, $default, $tag] = self::RULE_LABELS[$rule];

        return $this->formatter->format($key, $default, ['tag' => $tag]);
    }

    /**
     * @param array<array-key, mixed>      $fields
     * @param array<string, list<Finding>> $violations
     */
    private function collect(array $fields, string $prefix, int $depth, bool $strict, array &$violations): void
    {
        foreach ($fields as $field => $value) {
            $path = $prefix === '' ? (string) $field : $prefix . '.' . $field;

            if (\is_array($value)) {
                if ($depth + 1 >= self::MAX_DEPTH) {
                    $violations[$path] = [new Finding(Finding::RULE_TOO_DEEP, '')];
                    continue;
                }
                $this->collect($value, $path, $depth + 1, $strict, $violations);
                continue;
            }

            if (!\is_string($value) || $value === '') {
                continue;
            }

            $findings = $this->inspector->inspect($value, $strict);
            if ($findings !== []) {
                $violations[$path] = $findings;
            }
        }
    }
}
