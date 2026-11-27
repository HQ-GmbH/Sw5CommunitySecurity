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

namespace Sw5CommunitySecurity\Tests\Unit\Components\ApiAccess;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\ApiAccess\ApiAccessPolicy;

final class ApiAccessPolicyTest extends TestCase
{
    public function testWithoutEntriesEverythingIsAllowed(): void
    {
        $policy = new ApiAccessPolicy(" \n ");

        static::assertFalse($policy->isRestricted());
        static::assertTrue($policy->allows('203.0.113.7'));
        static::assertTrue($policy->allows(null));
        static::assertSame([], $policy->invalidEntries());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function addresses(): iterable
    {
        yield 'single IPv4, same' => ['192.0.2.10', '192.0.2.10', true];
        yield 'single IPv4, other' => ['192.0.2.10', '192.0.2.11', false];
        yield 'IPv4 /24, inside' => ['198.51.100.0/24', '198.51.100.254', true];
        yield 'IPv4 /24, outside' => ['198.51.100.0/24', '198.51.101.1', false];
        yield 'IPv4 /20, inside (partial byte)' => ['10.0.16.0/20', '10.0.31.255', true];
        yield 'IPv4 /20, outside (partial byte)' => ['10.0.16.0/20', '10.0.32.0', false];
        yield 'IPv4 /0 allows every IPv4' => ['0.0.0.0/0', '203.0.113.9', true];
        yield 'IPv4 range does not match IPv6' => ['0.0.0.0/0', '2001:db8::1', false];
        yield 'single IPv6, same (other notation)' => ['2001:db8::1', '2001:0db8:0:0:0:0:0:1', true];
        yield 'IPv6 /48, inside' => ['2001:db8:abcd::/48', '2001:db8:abcd:12::5', true];
        yield 'IPv6 /48, outside' => ['2001:db8:abcd::/48', '2001:db8:abce::5', false];
        yield 'IPv6 range does not match IPv4' => ['::/0', '192.0.2.1', false];
        yield 'IPv4 entry matches IPv4-mapped client' => ['192.0.2.0/24', '::ffff:192.0.2.77', true];
        yield 'IPv4 entry rejects other IPv4-mapped client' => ['192.0.2.0/24', '::ffff:198.51.100.1', false];
        yield 'IPv4-mapped entry matches IPv4 client' => ['::ffff:192.0.2.10', '192.0.2.10', true];
        yield 'IPv4-mapped range matches IPv4 client' => ['::ffff:192.0.2.0/120', '192.0.2.200', true];
        yield 'IPv4-mapped range rejects other IPv4 client' => ['::ffff:192.0.2.0/120', '192.0.3.1', false];
        yield 'IPv4-mapped entry in hex notation' => ['::ffff:c000:20a', '::ffff:192.0.2.10', true];
    }

    #[DataProvider('addresses')]
    public function testMatchesAddressesAndRanges(string $entry, string $client, bool $allowed): void
    {
        static::assertSame($allowed, (new ApiAccessPolicy($entry))->allows($client));
    }

    public function testSeveralEntriesWithDifferentSeparators(): void
    {
        $policy = new ApiAccessPolicy("192.0.2.10\n198.51.100.0/24, 2001:db8::/32;203.0.113.5 192.0.2.10");

        static::assertTrue($policy->isRestricted());
        static::assertSame(4, $policy->validEntryCount());
        static::assertTrue($policy->allows('203.0.113.5'));
        static::assertTrue($policy->allows('2001:db8:1::1'));
        static::assertFalse($policy->allows('203.0.113.6'));
    }

    public function testWideEntriesAreReported(): void
    {
        $policy = new ApiAccessPolicy('0.0.0.0/0 128.0.0.0/1 10.0.0.0/8 ::/0 2000::/3 2001:db8::/15 fc00::/7 ::ffff:0.0.0.0/96 ::ffff:10.0.0.0/104 192.0.2.10');

        static::assertSame(['0.0.0.0/0', '128.0.0.0/1', '::/0', '2000::/3', '2001:db8::/15', '::ffff:0.0.0.0/96'], $policy->wideEntries());
        static::assertSame([], (new ApiAccessPolicy('192.0.2.0/24 2001:db8::/32'))->wideEntries());
    }

    public function testMissingOrInvalidClientAddressIsRejected(): void
    {
        $policy = new ApiAccessPolicy('192.0.2.0/24');

        static::assertFalse($policy->allows(null));
        static::assertFalse($policy->allows(''));
        static::assertFalse($policy->allows('not-an-address'));
    }

    public function testInvalidEntriesAreReportedAndNeverOpenTheApi(): void
    {
        $policy = new ApiAccessPolicy('192.0.2.300 example.org 10.0.0.0/33 10.0.0.0/ 2001:db8::/129 10.0.0.0/x');

        static::assertTrue($policy->isRestricted());
        static::assertSame(0, $policy->validEntryCount());
        static::assertSame(['192.0.2.300', 'example.org', '10.0.0.0/33', '10.0.0.0/', '2001:db8::/129', '10.0.0.0/x'], $policy->invalidEntries());
        static::assertFalse($policy->allows('192.0.2.1'));
        static::assertFalse($policy->allows('10.0.0.1'));
    }

    public function testValidEntriesStillWorkNextToInvalidOnes(): void
    {
        $policy = new ApiAccessPolicy('192.0.2.0/24 typo');

        static::assertSame(['typo'], $policy->invalidEntries());
        static::assertTrue($policy->allows('192.0.2.77'));
        static::assertFalse($policy->allows('198.51.100.1'));
    }
}
