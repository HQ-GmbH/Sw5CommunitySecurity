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

namespace Sw5CommunitySecurity\Components\CustomerSession;

/**
 * Keeps the hash of the newest login token per customer account.
 */
interface LoginTokenStore
{
    public function find(int $customerId): ?string;

    public function save(int $customerId, string $tokenHash): void;
}
