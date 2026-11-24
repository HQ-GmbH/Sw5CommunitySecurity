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

namespace Sw5CommunitySecurity\Components\JavaScriptEscape;

/**
 * Hardens Smarty's escape:'javascript' modifier in compiled templates
 * (CVE-2023-28447, GHSA-7j98-h7fp-4vwj, fixed in Smarty 3.1.48).
 *
 * Shopware 5 ships Smarty 3.1.12. For a literal escape type its modifier
 * compiler (engine/Library/Smarty/plugins/modifiercompiler.escape.php,
 * case 'javascript') inlines strtr() with a fixed replacement list into the
 * compiled template. That list lacks the replacements Smarty added in 3.1.48
 * for "<!--", "<script", template literals ("`") and their placeholders
 * ("${"). This class swaps the inlined list for the complete one; the
 * generated code stays plain PHP without any reference to this plugin, so
 * compiled templates keep working after the plugin was removed.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 *
 * Third-party content: both replacement lists below are taken from Smarty
 * (https://github.com/smarty-php/smarty), libs/plugins/modifiercompiler.escape.php,
 * versions 3.1.12 (as shipped with Shopware 5.6.10/5.7.20) and v3.1.48,
 * author Rodney Rehm, copyright New Digital Group, Inc. and Uwe Tews.
 * SPDX-License-Identifier: LGPL-3.0-or-later (see THIRD-PARTY-NOTICES.md).
 */
final class JavaScriptEscapeHardening
{
    /**
     * Replacement list exactly as Smarty 3.1.12 emits it into compiled code
     * (modifiercompiler.escape.php, Shopware 5.6.10 and 5.7.20).
     */
    public const LEGACY_REPLACEMENTS_CODE = <<<'PHP'
        array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", "\n" => "\\n", "</" => "<\/" )
        PHP;

    /**
     * Replacements of escape:'javascript' as of Smarty 3.1.48
     * (libs/plugins/modifiercompiler.escape.php, case 'javascript').
     */
    public const REPLACEMENTS = [
        '\\' => '\\\\',
        "'" => "\\'",
        '"' => '\\"',
        "\r" => '\\r',
        "\n" => '\\n',
        '</' => '<\\/',
        '<!--' => '<\\!--',
        '<s' => '<\\s',
        '<S' => '<\\S',
        '`' => '\\`',
        '${' => '\\$\\{',
    ];

    private ?string $replacementsCode = null;

    /**
     * Replaces every inlined legacy replacement list in compiled template code.
     */
    public function harden(string $compiledCode): string
    {
        if (strpos($compiledCode, self::LEGACY_REPLACEMENTS_CODE) === false) {
            return $compiledCode;
        }

        return str_replace(self::LEGACY_REPLACEMENTS_CODE, $this->replacementsCode(), $compiledCode);
    }

    /**
     * Escapes a value the way hardened compiled templates do.
     */
    public static function escape(string $value): string
    {
        return strtr($value, self::REPLACEMENTS);
    }

    /**
     * PHP source of the complete replacement list, generated from
     * self::REPLACEMENTS so code and constant cannot diverge.
     */
    public function replacementsCode(): string
    {
        if ($this->replacementsCode === null) {
            $entries = [];
            foreach (self::REPLACEMENTS as $search => $replace) {
                $entries[] = var_export($search, true) . ' => ' . var_export($replace, true);
            }
            $this->replacementsCode = 'array(' . implode(', ', $entries) . ')';
        }

        return $this->replacementsCode;
    }
}
