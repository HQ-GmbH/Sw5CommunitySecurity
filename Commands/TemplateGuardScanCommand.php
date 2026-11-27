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
use Sw5CommunitySecurity\Components\TemplateGuard\StoredTemplateScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * bin/console sw5cs:template-guard:scan
 *
 * Lists stored templates that the template guard would reject. Read-only.
 * Exit code 0 = nothing found, 1 = findings (usable in monitoring/CI).
 */
final class TemplateGuardScanCommand extends Command
{
    public const NAME = 'sw5cs:template-guard:scan';

    /**
     * Snippet key => English default. Smarty tags are inserted as
     * placeholders, because snippets must not contain braces.
     */
    public const MESSAGES = [
        'console_template_scan_description' => 'Scans stored Smarty templates for disallowed constructs (read-only).',
        'console_template_scan_relaxed' => 'Without the strict [include] and [math] rules',
        'console_template_scan_none' => 'No disallowed Smarty constructs found.',
        'console_template_scan_findings' => '[count] finding(s). Please review the templates listed above in the backend; the plugin does not change stored content.',
        'console_column_source' => 'Source',
        'console_column_record' => 'Record',
        'console_column_field' => 'Field',
        'console_column_rule' => 'Rule',
    ];

    /**
     * @readonly
     */
    private StoredTemplateScanner $scanner;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(
        StoredTemplateScanner $scanner,
        MessageFormatter $messages
    ) {
        $this->scanner = $scanner;
        // configure() runs inside the parent constructor and needs the texts.
        $this->messages = $messages;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->text('console_template_scan_description'))
            ->addOption('relaxed', null, InputOption::VALUE_NONE, $this->text('console_template_scan_relaxed', ['include' => '{include}', 'math' => '{math}']));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->scanner->scan($input->getOption('relaxed') !== true);

        if ($results === []) {
            $io->success($this->text('console_template_scan_none'));

            return 0;
        }

        $io->table(
            [
                $this->text('console_column_source'),
                $this->text('console_column_record'),
                $this->text('console_column_field'),
                $this->text('console_column_rule'),
            ],
            array_map(
                static fn (array $result): array => [$result['source'], $result['record'], $result['field'], implode(', ', $result['rules'])],
                $results,
            ),
        );
        $io->warning($this->text('console_template_scan_findings', ['count' => (string) \count($results)]));

        return 1;
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
