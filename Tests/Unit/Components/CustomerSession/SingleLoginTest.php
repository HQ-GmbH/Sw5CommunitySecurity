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

namespace Sw5CommunitySecurity\Tests\Unit\Components\CustomerSession;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\CustomerSession\LoginTokenStore;
use Sw5CommunitySecurity\Components\CustomerSession\SingleLogin;

final class SingleLoginTest extends TestCase
{
    public function testNewestLoginIsCurrentOlderOneIsNot(): void
    {
        $login = new SingleLogin(self::store());

        $first = $login->register(7);
        static::assertTrue($login->isCurrent(7, $first));

        $second = $login->register(7);
        static::assertTrue($login->isCurrent(7, $second));
        static::assertFalse($login->isCurrent(7, $first));
    }

    public function testAccountsAreIndependent(): void
    {
        $login = new SingleLogin(self::store());

        $customerA = $login->register(1);
        $login->register(2);

        static::assertTrue($login->isCurrent(1, $customerA));
        static::assertFalse($login->isCurrent(2, $customerA));
    }

    public function testSessionsWithoutTokenOrStoredLoginStayValid(): void
    {
        $login = new SingleLogin(self::store());

        static::assertTrue($login->isCurrent(3, 'token-from-elsewhere'));
        $login->register(3);
        static::assertTrue($login->isCurrent(3, null));
        static::assertTrue($login->isCurrent(3, ''));
        static::assertTrue($login->isCurrent(3, ['not a string']));
    }

    public function testOnlyTheHashIsStored(): void
    {
        $store = self::store();
        $token = (new SingleLogin($store))->register(5);

        static::assertSame(64, \strlen($token));
        static::assertSame(hash('sha256', $token), $store->find(5));
        static::assertNotSame((new SingleLogin($store))->register(5), $token);
    }

    private static function store(): LoginTokenStore
    {
        return new class implements LoginTokenStore {
            /**
             * @var array<int, string>
             */
            private array $hashes = [];

            public function find(int $customerId): ?string
            {
                return $this->hashes[$customerId] ?? null;
            }

            public function save(int $customerId, string $tokenHash): void
            {
                $this->hashes[$customerId] = $tokenHash;
            }
        };
    }
}
