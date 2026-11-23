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
     * @readonly
     */
    private StoredTemplateScanner $scanner;

    public function __construct(
        StoredTemplateScanner $scanner,
    ) {
        $this->scanner = $scanner;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Prüft gespeicherte Smarty-Vorlagen auf nicht erlaubte Konstrukte (nur lesend) / Scans stored Smarty templates for disallowed constructs (read-only).')
            ->addOption('relaxed', null, InputOption::VALUE_NONE, 'Ohne die strengen Regeln für {include} und {math} / Without the strict {include} and {math} rules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->scanner->scan($input->getOption('relaxed') !== true);

        if ($results === []) {
            $io->success('Keine nicht erlaubten Smarty-Konstrukte gefunden / No disallowed Smarty constructs found.');

            return 0;
        }

        $io->table(
            ['Quelle / Source', 'Datensatz / Record', 'Feld / Field', 'Regel / Rule'],
            array_map(
                static fn (array $result): array => [$result['source'], $result['record'], $result['field'], implode(', ', $result['rules'])],
                $results
            )
        );
        $io->warning(\sprintf(
            '%d Fund(e). Bitte die genannten Vorlagen im Backend prüfen und anpassen; das Plugin ändert keine gespeicherten Inhalte. / %d finding(s). Please review the templates listed above in the backend; the plugin does not change stored content.',
            \count($results),
            \count($results)
        ));

        return 1;
    }
}
