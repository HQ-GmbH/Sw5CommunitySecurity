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

namespace Sw5CommunitySecurity\Components\PasswordPolicy;

/**
 * Rules for passwords of backend accounts, checked when a password is set in
 * the user administration.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH_LOWER_BOUND = 8;
    public const MIN_LENGTH_UPPER_BOUND = 64;
    public const DEFAULT_MIN_LENGTH = 12;

    public const RULE_TOO_SHORT = 'too_short';
    public const RULE_CHARACTER_CLASSES = 'character_classes';
    public const RULE_CONTAINS_USERNAME = 'contains_username';

    /**
     * English defaults; snippet keys "password_rejected" and "password_rule_<rule>".
     */
    public const REJECTED_MESSAGE = 'Community Security Guard: The password does not meet the password rules ([rules]).';

    public const MESSAGES_BY_RULE = [
        self::RULE_TOO_SHORT => 'at least [length] characters',
        self::RULE_CHARACTER_CLASSES => 'three of four character types (lower case, upper case, digits, special characters)',
        self::RULE_CONTAINS_USERNAME => 'must not contain the user name',
    ];

    /**
     * At least this many of: lower case, upper case, digits, other characters.
     */
    public const REQUIRED_CHARACTER_CLASSES = 3;

    /**
     * @return list<self::RULE_*>
     */
    public static function violations(string $password, string $username, int $minLength): array
    {
        $violations = [];

        if (mb_strlen($password, 'UTF-8') < $minLength) {
            $violations[] = self::RULE_TOO_SHORT;
        }

        $classes = 0;
        foreach (['/\p{Ll}/u', '/\p{Lu}/u', '/\d/u', '/[^\p{Ll}\p{Lu}\d]/u'] as $pattern) {
            if (preg_match($pattern, $password) === 1) {
                ++$classes;
            }
        }

        if ($classes < self::REQUIRED_CHARACTER_CLASSES) {
            $violations[] = self::RULE_CHARACTER_CLASSES;
        }

        $username = trim($username);
        if (mb_strlen($username, 'UTF-8') >= 3 && mb_stripos($password, $username, 0, 'UTF-8') !== false) {
            $violations[] = self::RULE_CONTAINS_USERNAME;
        }

        return $violations;
    }
}
