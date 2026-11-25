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

namespace Sw5CommunitySecurity\Tests\Unit\Components\TwoFactor;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\TwoFactor\Base32;
use Sw5CommunitySecurity\Components\TwoFactor\Totp;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLogin;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorRecord;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorStore;

final class TwoFactorLoginTest extends TestCase
{
    private const RAW_SECRET = '12345678901234567890';
    private const NOW = 1700000000;

    public function testUserWithoutSecondFactorIsNotAffected(): void
    {
        $login = new TwoFactorLogin(self::store());

        static::assertFalse($login->isEnrolled(1));
        static::assertSame([TwoFactorLogin::RESULT_NOT_ENROLLED, 0], $login->verify(1, '', self::NOW));
    }

    public function testEnrollRequiresValidCodeAndCodesAreSingleUse(): void
    {
        $login = new TwoFactorLogin(self::store());
        $secret = Base32::encode(self::RAW_SECRET);

        static::assertFalse($login->enroll(1, $secret, '000000', self::NOW));
        static::assertFalse($login->isEnrolled(1));
        static::assertTrue($login->enroll(1, $secret, self::code(0), self::NOW));
        static::assertTrue($login->isEnrolled(1));

        // The code used for enrolment cannot be used again.
        static::assertSame(TwoFactorLogin::RESULT_INVALID, $login->verify(1, self::code(0), self::NOW)[0]);
        static::assertSame(TwoFactorLogin::RESULT_VALID, $login->verify(1, self::code(1), self::NOW + 30)[0]);
        static::assertSame(TwoFactorLogin::RESULT_INVALID, $login->verify(1, self::code(1), self::NOW + 30)[0]);
    }

    public function testMissingCodeIsRejectedWithoutCountingAsFailure(): void
    {
        $store = self::store();
        $login = new TwoFactorLogin($store);
        $login->enroll(1, Base32::encode(self::RAW_SECRET), self::code(0), self::NOW);

        static::assertSame([TwoFactorLogin::RESULT_INVALID, 0], $login->verify(1, '', self::NOW));
        static::assertSame([TwoFactorLogin::RESULT_INVALID, 0], $login->verify(1, null, self::NOW));
        static::assertSame(0, self::record($store, 1)->failedAttempts);
    }

    public function testRepeatedWrongCodesLockTheAccountAndAValidCodeResetsTheCounter(): void
    {
        $store = self::store();
        $login = new TwoFactorLogin($store);
        $login->enroll(1, Base32::encode(self::RAW_SECRET), self::code(0), self::NOW);

        for ($i = 1; $i < TwoFactorLogin::LOCK_AFTER_FAILURES; ++$i) {
            static::assertSame([TwoFactorLogin::RESULT_INVALID, 0], $login->verify(1, '000000', self::NOW));
        }

        static::assertSame([TwoFactorLogin::RESULT_INVALID, 150], $login->verify(1, '000000', self::NOW));
        static::assertSame(TwoFactorLogin::RESULT_VALID, $login->verify(1, self::code(1), self::NOW + 30)[0]);
        static::assertSame(0, self::record($store, 1)->failedAttempts);
    }

    public function testLockDurationGrowsAndIsCapped(): void
    {
        static::assertSame(0, TwoFactorLogin::lockSeconds(4));
        static::assertSame(150, TwoFactorLogin::lockSeconds(5));
        static::assertSame(300, TwoFactorLogin::lockSeconds(10));
        static::assertSame(3600, TwoFactorLogin::lockSeconds(1000));
    }

    public function testDisableRequiresValidCodeResetDoesNot(): void
    {
        $login = new TwoFactorLogin(self::store());
        $login->enroll(1, Base32::encode(self::RAW_SECRET), self::code(0), self::NOW);
        $login->enroll(2, Base32::encode(self::RAW_SECRET), self::code(0), self::NOW);

        static::assertFalse($login->disable(1, '000000', self::NOW));
        static::assertTrue($login->disable(1, self::code(1), self::NOW + 30));
        static::assertFalse($login->isEnrolled(1));

        static::assertSame([2], $login->enrolledUserIds());
        $login->reset(2);
        static::assertSame([], $login->enrolledUserIds());
    }

    private static function code(int $stepOffset): string
    {
        return Totp::code(self::RAW_SECRET, Totp::step(self::NOW) + $stepOffset);
    }

    private static function record(TwoFactorStore $store, int $userId): TwoFactorRecord
    {
        $record = $store->find($userId);
        static::assertNotNull($record);

        return $record;
    }

    private static function store(): TwoFactorStore
    {
        return new class implements TwoFactorStore {
            /**
             * @var array<int, TwoFactorRecord>
             */
            private array $records = [];

            public function find(int $userId): ?TwoFactorRecord
            {
                return $this->records[$userId] ?? null;
            }

            public function enable(int $userId, string $secret, int $usedStep): void
            {
                $this->records[$userId] = new TwoFactorRecord($secret, $usedStep, 0);
            }

            public function markUsed(int $userId, int $step): void
            {
                $record = $this->records[$userId] ?? null;
                if ($record !== null) {
                    $this->records[$userId] = new TwoFactorRecord($record->secret, $step, 0);
                }
            }

            public function recordFailure(int $userId): int
            {
                $record = $this->records[$userId] ?? null;
                if ($record === null) {
                    return 0;
                }

                $this->records[$userId] = new TwoFactorRecord($record->secret, $record->lastUsedStep, $record->failedAttempts + 1);

                return $record->failedAttempts + 1;
            }

            public function disable(int $userId): void
            {
                unset($this->records[$userId]);
            }

            public function enabledUserIds(): array
            {
                return array_keys($this->records);
            }
        };
    }
}
