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

use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates missing protection files (.htaccess) in internal directories.
 * Existing files are never changed. Exit code 1 if a file could not be
 * created.
 */
final class ProtectionFilesRestoreCommand extends Command
{
    public const NAME = 'sw5cs:protection-files:restore';

    /**
     * @readonly
     */
    private ProtectionFiles $protectionFiles;

    /**
     * @readonly
     */
    private string $shopwareRoot;

    public function __construct(ProtectionFiles $protectionFiles, string $shopwareRoot)
    {
        $this->protectionFiles = $protectionFiles;
        $this->shopwareRoot = $shopwareRoot;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Legt fehlende Schutzdateien (.htaccess) in internen Verzeichnissen an / Creates missing protection files (.htaccess) in internal directories.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->protectionFiles->restore($this->shopwareRoot);

        if ($result->restored !== []) {
            $io->listing($result->restored);
        }

        if ($result->failed !== []) {
            $io->error(\sprintf('Nicht angelegt (Schreibrechte prüfen) / Not created (check write permissions): %s', implode(', ', $result->failed)));

            return 1;
        }

        $io->success($result->restored === []
            ? 'Keine Schutzdatei fehlte / No protection file was missing.'
            : \sprintf('%d Schutzdatei(en) wiederhergestellt / %d protection file(s) restored.', \count($result->restored), \count($result->restored)));

        return 0;
    }
}
