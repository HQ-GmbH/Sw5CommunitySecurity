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
 * code. Code attempts are therefore counted and locked in the plugin's own
 * table: from the fifth attempt without a valid code in a row the second
 * factor is locked for 30 seconds per attempt, at most one hour. The lock
 * applies to every check (login and switching off), is independent of
 * Shopware's lock and is set before the code is checked, so parallel
 * requests cannot test more codes.
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
     * @return array{self::RESULT_*, int} result and remaining lock in seconds (0 = not locked)
     */
    public function verify(int $userId, $code, int $now): array
    {
        $record = $this->store->find($userId);
        if ($record === null) {
            return [self::RESULT_NOT_ENROLLED, 0];
        }

        $code = \is_string($code) ? trim($code) : '';
        if ($code === '') {
            // A forgotten code is no guess and does not count as an attempt.
            return [self::RESULT_INVALID, self::remainingLock($record, $now)];
        }

        $attempts = $this->store->reserveAttempt($userId, $now);
        if ($attempts === null) {
            // Locked: the code is not checked at all.
            $record = $this->store->find($userId);

            return [self::RESULT_INVALID, $record !== null ? max(1, self::remainingLock($record, $now)) : 0];
        }

        $step = Totp::verify($record->secret, $code, $now, $record->lastUsedStep);
        if ($step !== null && $this->store->markUsed($userId, $step)) {
            return [self::RESULT_VALID, 0];
        }

        return [self::RESULT_INVALID, self::lockSeconds($attempts)];
    }

    public static function lockSeconds(int $failures): int
    {
        if ($failures < self::LOCK_AFTER_FAILURES) {
            return 0;
        }

        return min($failures * self::LOCK_SECONDS_PER_FAILURE, self::LOCK_SECONDS_MAX);
    }

    public function isLocked(int $userId, int $now): bool
    {
        $record = $this->store->find($userId);

        return $record !== null && self::remainingLock($record, $now) > 0;
    }

    /**
     * Confirms a new secret with a code from the app and stores it. An
     * existing second factor is not replaced; it has to be switched off
     * (with a valid code) or reset by an administrator first.
     */
    public function enroll(int $userId, string $secret, string $code, int $now): bool
    {
        if ($this->store->find($userId) !== null) {
            return false;
        }

        $step = Totp::verify($secret, $code, $now);
        if ($step === null) {
            return false;
        }

        return $this->store->enable($userId, $secret, $step);
    }

    /**
     * Switching off by the user requires a valid code; attempts count
     * towards the same lock as the login.
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

    private static function remainingLock(TwoFactorRecord $record, int $now): int
    {
        return $record->lockedUntil !== null && $record->lockedUntil > $now ? $record->lockedUntil - $now : 0;
    }
}
