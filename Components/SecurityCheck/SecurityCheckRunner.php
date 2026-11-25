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

namespace Sw5CommunitySecurity\Components\SecurityCheck;

use DateTimeImmutable;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs all checks and returns them as plain-text rows, most severe first.
 * Used by the console command and the backend module.
 */
final class SecurityCheckRunner
{
    /**
     * Fixed texts of the backend page; snippet key => English default.
     */
    public const LABELS = [
        'page_title' => 'Security check',
        'page_intro' => 'Overview of security-relevant settings. The check itself changes nothing. Module settings refer to the default shop.',
        'column_status' => 'Status',
        'column_check' => 'Check',
        'column_result' => 'Result',
        'status_ok' => 'OK',
        'status_info' => 'Note',
        'status_warning' => 'Warning',
        'status_error' => 'Error',
        'summary' => '[errors] error(s), [warnings] warning(s)',
        'restore_button' => 'Restore missing protection files',
        'restore_done' => 'The missing protection files were restored.',
        'restore_failed' => 'Some protection files could not be created. Check the write permissions of the directories listed above.',
        'restore_nothing' => 'No protection files were missing.',
        'restore_denied' => 'Your backend role is not allowed to restore protection files.',
    ];

    /**
     * @readonly
     */
    private EnvironmentCollector $collector;

    /**
     * @readonly
     */
    private SecurityCheck $check;

    /**
     * @readonly
     */
    private DefaultShopConfigSource $config;

    /**
     * @readonly
     */
    private MessageFormatter $formatter;

    public function __construct(EnvironmentCollector $collector, SecurityCheck $check, DefaultShopConfigSource $config, MessageFormatter $formatter)
    {
        $this->collector = $collector;
        $this->check = $check;
        $this->config = $config;
        $this->formatter = $formatter;
    }

    /**
     * @param Request|null $request the current web request; null on the console
     *
     * @return list<array{id: string, status: CheckResult::STATUS_*, title: string, message: string}>
     */
    public function run(?Request $request): array
    {
        $results = $this->check->evaluate(
            $this->collector->collect($request),
            $this->config->getForDefaultShop(),
            new DateTimeImmutable('today'),
        );

        return self::toRows($results, $this->formatter);
    }

    /**
     * @param list<array{id: string, status: CheckResult::STATUS_*, title: string, message: string}> $rows
     *
     * @return array<string, string> plain text, keyed like LABELS
     */
    public function labels(array $rows): array
    {
        $count = static fn (string $status): string => (string) \count(array_filter($rows, static fn (array $row): bool => $row['status'] === $status));

        $labels = [];
        foreach (self::LABELS as $key => $default) {
            $labels[$key] = $this->formatter->formatText($key, $default, [
                'errors' => $count(CheckResult::STATUS_ERROR),
                'warnings' => $count(CheckResult::STATUS_WARNING),
            ]);
        }

        return $labels;
    }

    /**
     * @param list<CheckResult> $results
     *
     * @return list<array{id: string, status: CheckResult::STATUS_*, title: string, message: string}>
     */
    public static function toRows(array $results, MessageFormatter $formatter): array
    {
        // Stable sort by severity (usort is not stable before PHP 8.0).
        $severity = array_flip(CheckResult::SEVERITY_ORDER);
        $keyed = [];
        foreach ($results as $index => $result) {
            $keyed[] = [$severity[$result->status], $index, $result];
        }
        sort($keyed);
        $results = array_column($keyed, 2);

        return array_map(
            static fn (CheckResult $result): array => [
                'id' => $result->id,
                'status' => $result->status,
                'title' => $formatter->formatText('check_' . $result->id, SecurityCheck::TITLES[$result->id] ?? $result->id),
                'message' => $formatter->formatText($result->messageKey, $result->defaultMessage, $result->placeholders),
            ],
            $results,
        );
    }
}
