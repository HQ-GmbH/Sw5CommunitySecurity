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
     * @readonly
     */
    private SecurityCheckRunner $runner;

    public function __construct(SecurityCheckRunner $runner)
    {
        $this->runner = $runner;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Prüft sicherheitsrelevante Einstellungen des Shops (nur lesend) / Checks security-relevant settings of the shop (read-only).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = $this->runner->run(null);

        $io->table(
            ['Status', 'Prüfung / Check', 'Ergebnis / Result'],
            array_map(static fn (array $row): array => [strtoupper($row['status']), $row['title'], $row['message']], $rows),
        );
        $io->note('PHP-Werte stammen aus der Kommandozeile und können vom Webserver abweichen; maßgeblich ist die Ansicht im Backend. / PHP values come from the command line and may differ from the web server; the backend view is authoritative.');

        $errors = \count(array_filter($rows, static fn (array $row): bool => $row['status'] === CheckResult::STATUS_ERROR));
        $warnings = \count(array_filter($rows, static fn (array $row): bool => $row['status'] === CheckResult::STATUS_WARNING));

        if ($errors > 0) {
            $io->error(\sprintf('%d Fehler, %d Warnung(en) / %d error(s), %d warning(s).', $errors, $warnings, $errors, $warnings));

            return 1;
        }

        if ($warnings > 0) {
            $io->warning(\sprintf('%d Warnung(en) / %d warning(s).', $warnings, $warnings));

            return 0;
        }

        $io->success('Keine Fehler oder Warnungen / No errors or warnings.');

        return 0;
    }
}
