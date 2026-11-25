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
     * @readonly
     */
    private TwoFactorLogin $twoFactor;

    /**
     * @readonly
     */
    private RowReader $rows;

    public function __construct(TwoFactorLogin $twoFactor, RowReader $rows)
    {
        $this->twoFactor = $twoFactor;
        $this->rows = $rows;
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Entfernt die Zwei-Faktor-Anmeldung eines Backend-Benutzers / Removes the two-factor login of a backend user.');
        $this->addArgument('username', InputArgument::REQUIRED, 'Backend-Benutzername / Backend user name');
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
            $io->error('Backend-Benutzer nicht gefunden / Backend user not found.');

            return 1;
        }

        $this->twoFactor->reset((int) $id);
        $io->success('Zwei-Faktor-Anmeldung entfernt / Two-factor login removed.');

        return 0;
    }
}
