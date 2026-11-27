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

use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLogin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the two-factor login of a backend user, e.g. after a lost device
 * when no administrator can log in any more.
 */
final class TwoFactorResetCommand extends Command
{
    public const NAME = 'sw5cs:two-factor:reset';

    /**
     * Snippet key => English default; two_factor_result_reset is shared with
     * the backend page.
     */
    public const MESSAGES = [
        'console_two_factor_reset_description' => 'Removes the two-factor login of a backend user.',
        'console_two_factor_reset_username' => 'Backend user name',
        'console_two_factor_reset_not_found' => 'Backend user not found.',
        'two_factor_result_reset' => 'The two-factor login of the user was removed.',
    ];

    /**
     * @readonly
     */
    private TwoFactorLogin $twoFactor;

    /**
     * @readonly
     */
    private RowReader $rows;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    public function __construct(TwoFactorLogin $twoFactor, RowReader $rows, MessageFormatter $messages)
    {
        $this->twoFactor = $twoFactor;
        $this->rows = $rows;
        // configure() runs inside the parent constructor and needs the texts.
        $this->messages = $messages;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription($this->text('console_two_factor_reset_description'));
        $this->addArgument('username', InputArgument::REQUIRED, $this->text('console_two_factor_reset_username'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        if (!\is_string($username)) {
            return 2;
        }

        $id = $this->rows->fetchAll('SELECT `id` FROM `s_core_auth` WHERE `username` = ?', [$username])[0]['id'] ?? null;
        if (!is_numeric($id)) {
            $io->error($this->text('console_two_factor_reset_not_found'));

            return 1;
        }

        $this->twoFactor->reset((int) $id);
        $io->success($this->text('two_factor_result_reset'));

        return 0;
    }

    /**
     * @param key-of<self::MESSAGES> $key
     */
    private function text(string $key): string
    {
        return $this->messages->formatText($key, self::MESSAGES[$key]);
    }
}
