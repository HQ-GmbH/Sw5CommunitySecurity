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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\TwoFactor\Base32;
use Sw5CommunitySecurity\Components\TwoFactor\Totp;

final class TotpTest extends TestCase
{
    /**
     * RFC 6238, Appendix B (SHA-1, 8 digits, secret "12345678901234567890").
     *
     * @return iterable<string, array{int, string}>
     */
    public static function rfc6238Vectors(): iterable
    {
        yield '59' => [59, '94287082'];
        yield '1111111109' => [1111111109, '07081804'];
        yield '1111111111' => [1111111111, '14050471'];
        yield '1234567890' => [1234567890, '89005924'];
        yield '2000000000' => [2000000000, '69279037'];
        yield '20000000000' => [20000000000, '65353130'];
    }

    #[DataProvider('rfc6238Vectors')]
    public function testRfc6238TestVectors(int $time, string $expected): void
    {
        static::assertSame($expected, Totp::code('12345678901234567890', Totp::step($time), 8));
    }

    public function testRfc4648Base32Vectors(): void
    {
        $vectors = ['' => '', 'f' => 'MY', 'fo' => 'MZXQ', 'foo' => 'MZXW6', 'foob' => 'MZXW6YQ', 'fooba' => 'MZXW6YTB', 'foobar' => 'MZXW6YTBOI'];
        foreach ($vectors as $plain => $encoded) {
            static::assertSame($encoded, Base32::encode($plain));
            static::assertSame($plain, Base32::decode($encoded));
        }

        static::assertSame('foobar', Base32::decode('mzxw 6ytb oi======'));
        static::assertNull(Base32::decode('MZXW1'));
    }

    public function testVerifyAcceptsAdjacentStepsOnly(): void
    {
        $secret = Base32::encode('12345678901234567890');
        $now = 1111111111;
        $code = static fn (int $offset): string => Totp::code('12345678901234567890', Totp::step($now) + $offset);

        static::assertSame(Totp::step($now), Totp::verify($secret, $code(0), $now));
        static::assertSame(Totp::step($now) - 1, Totp::verify($secret, $code(-1), $now));
        static::assertSame(Totp::step($now) + 1, Totp::verify($secret, $code(1), $now));
        static::assertNull(Totp::verify($secret, $code(2), $now));
        static::assertNull(Totp::verify($secret, $code(-2), $now));
        static::assertSame(Totp::step($now), Totp::verify($secret, substr($code(0), 0, 3) . ' ' . substr($code(0), 3), $now));
    }

    public function testVerifyRejectsReplayAndMalformedInput(): void
    {
        $secret = Base32::encode('12345678901234567890');
        $now = 1111111111;
        $code = Totp::code('12345678901234567890', Totp::step($now));

        static::assertNull(Totp::verify($secret, $code, $now, Totp::step($now)));
        static::assertNull(Totp::verify($secret, '12345', $now));
        static::assertNull(Totp::verify($secret, 'abcdef', $now));
        static::assertNull(Totp::verify('not base32!', $code, $now));
        static::assertNull(Totp::verify('', $code, $now));
    }

    public function testGeneratedSecretHas160Bits(): void
    {
        $secret = Totp::generateSecret();

        static::assertSame(32, \strlen($secret));
        static::assertSame(20, \strlen((string) Base32::decode($secret)));
        static::assertNotSame($secret, Totp::generateSecret());
    }

    public function testUri(): void
    {
        static::assertSame(
            'otpauth://totp/Shopware%20Backend%3Ademo?secret=MZXW6YTBOI&issuer=Shopware%20Backend&algorithm=SHA1&digits=6&period=30',
            Totp::uri('MZXW6YTBOI', 'Shopware Backend', 'demo'),
        );
    }
}
