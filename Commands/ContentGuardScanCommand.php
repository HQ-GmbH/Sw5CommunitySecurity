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

use Sw5CommunitySecurity\Components\ContentGuard\StoredContentScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * bin/console sw5cs:content-guard:scan – read-only scan of stored editor
 * content (HTML) for script. Exit code 1 if anything is found, so it can be
 * used in monitoring and deployment scripts.
 */
final class ContentGuardScanCommand extends Command
{
    public const NAME = 'sw5cs:content-guard:scan';

    /**
     * @readonly
     */
    private StoredContentScanner $scanner;

    public function __construct(StoredContentScanner $scanner)
    {
        $this->scanner = $scanner;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Prüft gespeicherte Editor-Inhalte auf Skript (nur lesend) / Scans stored editor content for script (read-only).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->scanner->scan();

        if ($results === []) {
            $io->success('Kein Skript in Editor-Inhalten gefunden / No script found in editor content.');

            return 0;
        }

        $io->table(
            ['Quelle / Source', 'Datensatz / Record', 'Feld / Field', 'Regel / Rule'],
            array_map(
                static fn (array $result): array => [$result['source'], $result['record'], $result['field'], implode(', ', $result['rules'])],
                $results,
            ),
        );
        $io->warning(\sprintf(
            '%d Fund(e). Bitte die genannten Inhalte im Backend prüfen und anpassen; das Plugin ändert keine gespeicherten Inhalte. / %d finding(s). Please review the content listed above in the backend; the plugin does not change stored content.',
            \count($results),
            \count($results),
        ));

        return 1;
    }
}
