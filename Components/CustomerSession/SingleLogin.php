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
 * "One login per customer account": every login creates a random token that
 * is kept in the customer's session; only the hash of the newest token is
 * stored. A session whose token is no longer the newest one belongs to an
 * older login and is logged out.
 *
 * Sessions without a token (started before the option was enabled) stay
 * valid until they expire or the customer logs in again, so enabling the
 * option never logs out all customers at once.
 */
final class SingleLogin
{
    public const SESSION_KEY = 'sw5csLoginToken';

    /**
     * @readonly
     */
    private LoginTokenStore $store;

    public function __construct(LoginTokenStore $store)
    {
        $this->store = $store;
    }

    /**
     * Registers a new login and returns the token for the session.
     */
    public function register(int $customerId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->store->save($customerId, self::hash($token));

        return $token;
    }

    /**
     * @param mixed $sessionToken value stored in the session, if any
     */
    public function isCurrent(int $customerId, $sessionToken): bool
    {
        if (!\is_string($sessionToken) || $sessionToken === '') {
            return true;
        }

        $expected = $this->store->find($customerId);
        if ($expected === null) {
            return true;
        }

        return hash_equals($expected, self::hash($sessionToken));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
