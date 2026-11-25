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

/**
 * Two-factor settings of one backend user.
 */
final class TwoFactorRecord
{
    /**
     * @readonly
     */
    public string $secret;

    /**
     * @readonly
     */
    public ?int $lastUsedStep;

    /**
     * @readonly
     */
    public int $failedAttempts;

    public function __construct(string $secret, ?int $lastUsedStep, int $failedAttempts)
    {
        $this->secret = $secret;
        $this->lastUsedStep = $lastUsedStep;
        $this->failedAttempts = $failedAttempts;
    }
}
