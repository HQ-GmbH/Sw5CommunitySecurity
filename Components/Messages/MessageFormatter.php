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

namespace Sw5CommunitySecurity\Components\Messages;

/**
 * Builds HTML-safe messages for the backend: the text from the catalog and
 * every placeholder value are escaped, and placeholders use Shopware's
 * "[name]" style, so snippet texts never contain Smarty syntax.
 */
final class MessageFormatter
{
    public function __construct(
        private readonly MessageCatalog $catalog,
    ) {
    }

    /**
     * @param array<string, string> $placeholders placeholder name (without brackets) => raw value
     */
    public function format(string $key, string $default, array $placeholders = []): string
    {
        $replacements = [];
        foreach ($placeholders as $name => $value) {
            $replacements['[' . $name . ']'] = self::escape($value);
        }

        return strtr(self::escape($this->catalog->get($key, $default)), $replacements);
    }

    /**
     * Same as format(), but placeholder values are inserted as given; they
     * must already be escaped.
     *
     * @param array<string, string> $placeholders placeholder name (without brackets) => escaped value
     */
    public function formatWithEscapedValues(string $key, string $default, array $placeholders): string
    {
        $replacements = [];
        foreach ($placeholders as $name => $value) {
            $replacements['[' . $name . ']'] = $value;
        }

        return strtr(self::escape($this->catalog->get($key, $default)), $replacements);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
