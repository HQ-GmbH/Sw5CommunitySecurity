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

use Sw5CommunitySecurity\Components\Messages\DefaultMessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;

/**
 * Inspects named fields (also nested, e.g. translation data) with the
 * HtmlInspector and builds the rejection message.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class ContentGuard
{
    public const MAX_DEPTH = 8;

    public const RULE_TOO_DEEP = 'too-deep';

    /**
     * Rule => [snippet key, default text].
     */
    private const RULE_LABELS = [
        HtmlInspector::RULE_SCRIPT_ELEMENT => ['content_rule_script_element', '<script>'],
        HtmlInspector::RULE_EVENT_HANDLER => ['content_rule_event_handler', 'Event-Attribute (on…) / event attributes (on…)'],
        HtmlInspector::RULE_SCRIPT_URL => ['content_rule_script_url', 'Skript-Adressen (javascript: u. a.) / script addresses (javascript: etc.)'],
        HtmlInspector::RULE_SRCDOC => ['content_rule_srcdoc', 'srcdoc'],
        HtmlInspector::RULE_UNPARSABLE => ['content_rule_unparsable', 'nicht vollständig prüfbares Markup (z. B. zu tief verschachtelt) / markup that cannot be checked completely (e.g. nested too deeply)'],
        self::RULE_TOO_DEEP => ['rule_too_deep', 'zu tief verschachtelte Daten / data nested too deeply'],
    ];

    private const MESSAGE_KEY = 'content_rejected';

    private const MESSAGE_DEFAULT = 'Community Security Guard: Der Inhalt enthält Skript und wurde nicht übernommen'
        . ' / The content contains script and was not accepted ([details]).';

    /**
     * @readonly
     */
    private HtmlInspector $inspector;

    /**
     * @readonly
     */
    private MessageFormatter $formatter;

    public function __construct(HtmlInspector $inspector, ?MessageFormatter $formatter = null)
    {
        $this->inspector = $inspector;
        $this->formatter = $formatter ?? new MessageFormatter(new DefaultMessageCatalog());
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, list<string>> field path => rules; numeric field names become integer keys
     */
    public function findViolations(array $fields): array
    {
        $violations = [];
        $this->collect($fields, '', 0, $violations);

        return $violations;
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @throws UnsafeContentException
     */
    public function assertSafe(array $fields): void
    {
        $violations = $this->findViolations($fields);
        if ($violations !== []) {
            throw new UnsafeContentException($this->buildMessage($violations));
        }
    }

    /**
     * @param array<array-key, list<string>> $violations
     */
    public function buildMessage(array $violations): string
    {
        $parts = [];
        foreach ($violations as $field => $rules) {
            $labels = array_map(fn (string $rule): string => $this->ruleLabel($rule), $rules);
            // PHP turns numeric field names into integer keys.
            $parts[] = MessageFormatter::escape((string) $field) . ': ' . implode(', ', $labels);
        }

        return $this->formatter->formatWithEscapedValues(self::MESSAGE_KEY, self::MESSAGE_DEFAULT, ['details' => implode('; ', $parts)]);
    }

    private function ruleLabel(string $rule): string
    {
        if (!isset(self::RULE_LABELS[$rule])) {
            return MessageFormatter::escape($rule);
        }

        [$key, $default] = self::RULE_LABELS[$rule];

        return $this->formatter->format($key, $default);
    }

    /**
     * @param array<array-key, mixed>        $fields
     * @param array<array-key, list<string>> $violations
     */
    private function collect(array $fields, string $prefix, int $depth, array &$violations): void
    {
        foreach ($fields as $field => $value) {
            $path = $prefix === '' ? (string) $field : $prefix . '.' . $field;

            if (\is_array($value)) {
                if ($depth + 1 >= self::MAX_DEPTH) {
                    $violations[$path] = [self::RULE_TOO_DEEP];

                    continue;
                }
                $this->collect($value, $path, $depth + 1, $violations);

                continue;
            }

            if (!\is_string($value) || $value === '') {
                continue;
            }

            $rules = $this->inspector->inspect($value);
            if ($rules !== []) {
                $violations[$path] = $rules;
            }
        }
    }
}
