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
 * Checks a set of template fields and builds the error message for the
 * backend. Free of Shopware dependencies to keep it unit-testable.
 */
final class TemplateGuard
{
    private const RULE_LABELS = [
        Finding::RULE_EXTENDS => '{extends}',
        Finding::RULE_FETCH => '{fetch}',
        Finding::RULE_PHP => '{php}',
        Finding::RULE_FUNCTION_NAME => '{function} mit ungültigem Namen / with invalid name',
        Finding::RULE_BLOCK_NAME => '{block} mit ungültigem Namen / with invalid name',
        Finding::RULE_TEMPLATE_OBJECT => '$smarty.template_object',
    ];

    public function __construct(
        private readonly TemplateInspector $inspector,
    ) {
    }

    /**
     * @param array<array-key, mixed> $fields field name => template; non-string values are ignored
     *
     * @return array<string, list<Finding>> field name => findings, only fields with findings
     */
    public function findViolations(array $fields): array
    {
        $violations = [];

        foreach ($fields as $field => $value) {
            if (!\is_string($value) || $value === '') {
                continue;
            }

            $findings = $this->inspector->inspect($value);
            if ($findings !== []) {
                $violations[(string) $field] = $findings;
            }
        }

        return $violations;
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @throws UnsafeTemplateException
     */
    public function assertSafe(array $fields): void
    {
        $violations = $this->findViolations($fields);

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
            $rules = array_unique(array_map(
                static fn (Finding $finding): string => self::RULE_LABELS[$finding->rule] ?? $finding->rule,
                $findings
            ));
            $parts[] = sprintf('%s: %s', $this->escape($field), implode(', ', array_map([$this, 'escape'], $rules)));
        }

        return 'Community Security Guard: Die Vorlage enthält nicht erlaubte Smarty-Konstrukte und wurde nicht übernommen'
            . ' / The template contains disallowed Smarty constructs and was not accepted ('
            . implode('; ', $parts) . ').';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
