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
 * Second factor for the backend login.
 *
 * Shopware resets the counter of failed logins whenever the password is
 * correct (Auth adapter, 5.6.10/5.7.20), so its lockout does not protect the
 * code. Wrong codes are therefore counted separately; from the fifth wrong
 * code in a row the account is locked for 30 seconds per failure, at most
 * one hour.
 */
final class TwoFactorLogin
{
    public const RESULT_NOT_ENROLLED = 'not-enrolled';
    public const RESULT_VALID = 'valid';
    public const RESULT_INVALID = 'invalid';

    public const LOCK_AFTER_FAILURES = 5;
    public const LOCK_SECONDS_PER_FAILURE = 30;
    public const LOCK_SECONDS_MAX = 3600;

    /**
     * @readonly
     */
    private TwoFactorStore $store;

    public function __construct(TwoFactorStore $store)
    {
        $this->store = $store;
    }

    public function isEnrolled(int $userId): bool
    {
        return $this->store->find($userId) !== null;
    }

    /**
     * @param mixed $code value from the login form
     *
     * @return array{self::RESULT_*, int} result and lock duration in seconds (0 = no lock)
     */
    public function verify(int $userId, $code, int $now): array
    {
        $record = $this->store->find($userId);
        if ($record === null) {
            return [self::RESULT_NOT_ENROLLED, 0];
        }

        $code = \is_string($code) ? trim($code) : '';
        if ($code === '') {
            // A forgotten code is no guess and does not count as a failure.
            return [self::RESULT_INVALID, 0];
        }

        $step = Totp::verify($record->secret, $code, $now, $record->lastUsedStep);
        if ($step !== null) {
            $this->store->markUsed($userId, $step);

            return [self::RESULT_VALID, 0];
        }

        return [self::RESULT_INVALID, self::lockSeconds($this->store->recordFailure($userId))];
    }

    public static function lockSeconds(int $failures): int
    {
        if ($failures < self::LOCK_AFTER_FAILURES) {
            return 0;
        }

        return min($failures * self::LOCK_SECONDS_PER_FAILURE, self::LOCK_SECONDS_MAX);
    }

    /**
     * Confirms a new secret with a code from the app and stores it.
     */
    public function enroll(int $userId, string $secret, string $code, int $now): bool
    {
        $step = Totp::verify($secret, $code, $now);
        if ($step === null) {
            return false;
        }

        $this->store->enable($userId, $secret, $step);

        return true;
    }

    /**
     * Switching off by the user requires a valid code.
     */
    public function disable(int $userId, string $code, int $now): bool
    {
        [$result] = $this->verify($userId, $code, $now);
        if ($result !== self::RESULT_VALID) {
            return false;
        }

        $this->store->disable($userId);

        return true;
    }

    /**
     * Removal by an administrator or on the console, e.g. after a lost device.
     */
    public function reset(int $userId): void
    {
        $this->store->disable($userId);
    }

    /**
     * @return list<int>
     */
    public function enrolledUserIds(): array
    {
        return $this->store->enabledUserIds();
    }
}
