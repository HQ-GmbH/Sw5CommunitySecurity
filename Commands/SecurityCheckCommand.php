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

namespace Sw5CommunitySecurity\Commands;

use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\SecurityCheck\CheckResult;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheckRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only overview of security-relevant settings. Exit code 1 if at least
 * one check reports an error, so the command can be used in monitoring.
 */
final class SecurityCheckCommand extends Command
{
    public const NAME = 'sw5cs:security-check';

    /**
     * Snippet key => English default; column_* and summary are shared with
     * the backend page.
     */
    public const MESSAGES = [
        'console_security_check_description' => 'Checks security-relevant settings of the shop (read-only).',
        'console_security_check_note' => 'PHP values come from the command line and may differ from the web server; the backend view is authoritative.',
        'console_security_check_warnings' => '[warnings] warning(s).',
        'console_security_check_clean' => 'No errors or warnings.',
        'column_status' => 'Status',
        'column_check' => 'Check',
        'column_result' => 'Result',
        'summary' => '[errors] error(s), [warnings] warning(s)',
    ];

    /**
     * @readonly
     */
    private SecurityCheckRunner $runner;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(SecurityCheckRunner $runner, MessageFormatter $messages)
    {
        $this->runner = $runner;
        // configure() runs inside the parent constructor and needs the texts.
        $this->messages = $messages;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription($this->text('console_security_check_description'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = $this->runner->run(null);

        // The status column keeps the fixed codes (OK, INFO, WARNING, ERROR) for scripts.
        $io->table(
            [$this->text('column_status'), $this->text('column_check'), $this->text('column_result')],
            array_map(static fn (array $row): array => [strtoupper($row['status']), $row['title'], $row['message']], $rows),
        );
        $io->note($this->text('console_security_check_note'));

        $errors = \count(array_filter($rows, static fn (array $row): bool => $row['status'] === CheckResult::STATUS_ERROR));
        $warnings = \count(array_filter($rows, static fn (array $row): bool => $row['status'] === CheckResult::STATUS_WARNING));

        if ($errors > 0) {
            $io->error($this->text('summary', ['errors' => (string) $errors, 'warnings' => (string) $warnings]));

            return 1;
        }

        if ($warnings > 0) {
            $io->warning($this->text('console_security_check_warnings', ['warnings' => (string) $warnings]));

            return 0;
        }

        $io->success($this->text('console_security_check_clean'));

        return 0;
    }

    /**
     * @param key-of<self::MESSAGES> $key
     * @param array<string, string>  $placeholders
     */
    private function text(string $key, array $placeholders = []): string
    {
        return $this->messages->formatText($key, self::MESSAGES[$key], $placeholders);
    }
}
