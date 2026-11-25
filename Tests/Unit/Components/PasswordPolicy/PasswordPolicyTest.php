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

namespace Sw5CommunitySecurity\Tests\Unit\Components\PasswordPolicy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\PasswordPolicy\PasswordPolicy;

final class PasswordPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int, list<string>}>
     */
    public static function passwords(): iterable
    {
        yield 'strong' => ['Kaffee-Tasse-42', 'demo', 12, []];
        yield 'three classes without digits' => ['Kaffee-Tasse-zwei', 'demo', 12, []];
        yield 'umlauts count as letters' => ['Größenänderung1', 'demo', 12, []];
        yield 'too short' => ['Kurz-1a', 'demo', 12, [PasswordPolicy::RULE_TOO_SHORT]];
        yield 'length counts characters, not bytes' => ['Äöü-12345a', 'demo', 10, []];
        yield 'only lower case' => ['kaffeetassezwei', 'demo', 12, [PasswordPolicy::RULE_CHARACTER_CLASSES]];
        yield 'two classes' => ['kaffeetasse42', 'demo', 12, [PasswordPolicy::RULE_CHARACTER_CLASSES]];
        yield 'contains user name, any case' => ['Mein-ADMIN-2026', 'admin', 12, [PasswordPolicy::RULE_CONTAINS_USERNAME]];
        yield 'very short user names are ignored' => ['Kaffee-Tasse-42', 'ka', 12, []];
        yield 'everything wrong' => ['admin', 'admin', 12, [PasswordPolicy::RULE_TOO_SHORT, PasswordPolicy::RULE_CHARACTER_CLASSES, PasswordPolicy::RULE_CONTAINS_USERNAME]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('passwords')]
    public function testViolations(string $password, string $username, int $minLength, array $expected): void
    {
        static::assertSame($expected, PasswordPolicy::violations($password, $username, $minLength));
    }

    public function testEveryRuleHasAMessage(): void
    {
        static::assertSame(
            [PasswordPolicy::RULE_TOO_SHORT, PasswordPolicy::RULE_CHARACTER_CLASSES, PasswordPolicy::RULE_CONTAINS_USERNAME],
            array_keys(PasswordPolicy::MESSAGES_BY_RULE),
        );
    }
}
