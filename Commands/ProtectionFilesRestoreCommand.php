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
     * Snippet key => English default; restore_nothing is shared with the
     * backend page.
     */
    public const MESSAGES = [
        'console_protection_files_description' => 'Creates missing protection files (.htaccess) in internal directories.',
        'console_protection_files_failed' => 'Not created (check write permissions): [files]',
        'console_protection_files_restored' => '[count] protection file(s) restored.',
        'restore_nothing' => 'No protection files were missing.',
    ];

    /**
     * @readonly
     */
    private ProtectionFiles $protectionFiles;

    /**
     * @readonly
     */
    private string $shopwareRoot;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(ProtectionFiles $protectionFiles, string $shopwareRoot, MessageFormatter $messages)
    {
        $this->protectionFiles = $protectionFiles;
        $this->shopwareRoot = $shopwareRoot;
        // configure() runs inside the parent constructor and needs the texts.
        $this->messages = $messages;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription($this->text('console_protection_files_description'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->protectionFiles->restore($this->shopwareRoot);

        if ($result->restored !== []) {
            $io->listing($result->restored);
        }

        if ($result->failed !== []) {
            $io->error($this->text('console_protection_files_failed', ['files' => implode(', ', $result->failed)]));

            return 1;
        }

        $io->success($result->restored === []
            ? $this->text('restore_nothing')
            : $this->text('console_protection_files_restored', ['count' => (string) \count($result->restored)]));

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
