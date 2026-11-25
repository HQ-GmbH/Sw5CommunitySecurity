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

namespace Sw5CommunitySecurity\Components\TwoFactor;

interface TwoFactorStore
{
    public function find(int $userId): ?TwoFactorRecord;

    public function enable(int $userId, string $secret, int $usedStep): void;

    public function markUsed(int $userId, int $step): void;

    /**
     * Increments and returns the number of consecutive failed codes.
     */
    public function recordFailure(int $userId): int;

    public function disable(int $userId): void;

    /**
     * @return list<int>
     */
    public function enabledUserIds(): array;
}
