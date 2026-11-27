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

    /**
     * Stores a new set-up. Returns false if the user already has one; an
     * existing second factor is never replaced this way.
     */
    public function enable(int $userId, string $secret, int $usedStep): bool;

    /**
     * Marks a time step as used, resets the attempt counter and lifts the
     * lock. Atomic: returns false if the step (or a later one) was already
     * used, e.g. by a parallel request with the same code.
     */
    public function markUsed(int $userId, int $step): bool;

    /**
     * Counts a code attempt before the code is checked. Atomic: returns null
     * while the account is locked (no check allowed), otherwise the number
     * of consecutive attempts without a valid code. From the attempt that
     * reaches TwoFactorLogin::LOCK_AFTER_FAILURES the account is locked in
     * the same statement, so parallel requests cannot check more codes.
     */
    public function reserveAttempt(int $userId, int $now): ?int;

    public function disable(int $userId): void;

    /**
     * @return list<int>
     */
    public function enabledUserIds(): array;
}
