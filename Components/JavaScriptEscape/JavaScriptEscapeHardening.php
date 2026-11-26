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
 * ("${"). This class swaps the inlined list for the complete one.
 *
 * For an escape type that is only known at runtime (e.g. escape:$type), the
 * modifier compiler falls back to smarty_modifier_escape()
 * (plugins/modifier.escape.php), which uses the same old list. Such calls are
 * wrapped in a closure that applies the complete list for "javascript" and
 * passes every other type to smarty_modifier_escape() unchanged.
 *
 * A modifier "escape" registered as object method (the product export,
 * engine/Shopware/Core/sExport.php, sEscapeString) is compiled to a direct
 * method call; its "javascript" case uses the old list as well. Such calls are
 * wrapped the same way.
 *
 * The generated code stays plain PHP without any reference to this plugin, so
 * compiled templates keep working after the plugin was removed. Nocache
 * sections (only with Smarty's output cache) are stored inside single-quoted
 * PHP strings (smarty_internal_templatecompilerbase.php, addcslashes); there
 * the same code is inserted with the same escaping.
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

    /**
     * Name of Smarty's runtime escape function as called in compiled code.
     */
    public const RUNTIME_FUNCTION = 'smarty_modifier_escape';

    /**
     * Start of a call of a registered object method for the modifier "escape"
     * as Smarty 3.1.12 compiles it (smarty_internal_compile_private_modifier.php).
     */
    public const REGISTERED_CALL_PREFIX = '$_smarty_tpl->smarty->registered_plugins[Smarty::PLUGIN_MODIFIER][\'escape\'][0][0]->';

    /**
     * Calls of the runtime function; not inside quotes (the plugin header
     * names it as a string), not as a method or after a namespace separator.
     */
    private const RUNTIME_CALL_PATTERN = '/(?<![\w\'"$>:\\\\])smarty_modifier_escape\s*\(/';

    private const NOCACHE_MARKER = '%%SmartyNocache:';

    private const NOCACHE_SECTION_PATTERN = '/(\/\*%%SmartyNocache:[^%]+%%\*\/.*?\/\*\/%%SmartyNocache:[^%]+%%\*\/)/s';

    private ?string $replacementsCode = null;

    /**
     * Replaces every inlined legacy replacement list in compiled template code.
     */
    /**
     * Cheap pre-check: true if the compiled code may contain something to harden.
     */
    public static function mayNeedHardening(string $compiledCode): bool
    {
        return strpos($compiledCode, self::LEGACY_REPLACEMENTS_CODE) !== false
            || strpos($compiledCode, self::RUNTIME_FUNCTION) !== false
            || strpos($compiledCode, addcslashes(self::LEGACY_REPLACEMENTS_CODE, '\'\\')) !== false
            || strpos($compiledCode, "[Smarty::PLUGIN_MODIFIER]['escape']") !== false
            || strpos($compiledCode, "[Smarty::PLUGIN_MODIFIER][\\'escape\\']") !== false;
    }

    public function harden(string $compiledCode): string
    {
        if (strpos($compiledCode, self::NOCACHE_MARKER) === false) {
            return $this->hardenSection($compiledCode, false);
        }

        $parts = preg_split(self::NOCACHE_SECTION_PATTERN, $compiledCode, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (!\is_array($parts)) {
            return $compiledCode;
        }

        $hardened = '';
        foreach ($parts as $index => $part) {
            // Odd indexes are the captured nocache sections.
            $hardened .= $this->hardenSection($part, $index % 2 === 1);
        }

        return $hardened;
    }

    /**
     * Immediately invoked closure replacing "<registered escape object>-><method>(":
     * for "javascript" the complete list, otherwise the registered method itself.
     * The method name comes from the compiled code (\w+ only).
     */
    public function registeredWrapperCode(string $method): string
    {
        return '(static function ($object, $method, ...$args) {'
            . ' return isset($args[1]) && $args[1] === \'javascript\' ? strtr((string) $args[0], ' . $this->replacementsCode() . ')'
            . ' : $object->$method(...$args); })'
            . '(' . rtrim(self::REGISTERED_CALL_PREFIX, '->') . ', \'' . $method . '\', ';
    }

    /**
     * Closure with the signature of smarty_modifier_escape() (Smarty 3.1.12);
     * it calls the original through call_user_func() so the pattern does not
     * match it again.
     */
    public function runtimeWrapperCode(): string
    {
        return '(static function ($string, $esc_type = \'html\', $char_set = null, $double_encode = true) {'
            . ' return $esc_type === \'javascript\' ? strtr((string) $string, ' . $this->replacementsCode() . ')'
            . ' : \call_user_func(\'' . self::RUNTIME_FUNCTION . '\', $string, $esc_type, $char_set, $double_encode); })';
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

    /**
     * @param bool $quoted true inside a nocache section: code there is escaped for a single-quoted string
     */
    private function hardenSection(string $code, bool $quoted): string
    {
        $quote = static fn (string $value): string => $quoted ? addcslashes($value, '\'\\') : $value;

        $legacy = $quote(self::LEGACY_REPLACEMENTS_CODE);
        if (strpos($code, $legacy) !== false) {
            $code = str_replace($legacy, $quote($this->replacementsCode()), $code);
        }

        // Callbacks: the inserted code contains backslashes, which preg_replace() would interpret.
        if (strpos($code, self::RUNTIME_FUNCTION) !== false) {
            $wrapper = $quote($this->runtimeWrapperCode() . '(');
            $code = (string) preg_replace_callback(self::RUNTIME_CALL_PATTERN, static fn (): string => $wrapper, $code);
        }

        $prefix = $quote(self::REGISTERED_CALL_PREFIX);
        if (strpos($code, $prefix) !== false) {
            $code = (string) preg_replace_callback(
                '/' . preg_quote($prefix, '/') . '(\w+)\s*\(/',
                fn (array $match): string => $quote($this->registeredWrapperCode($match[1])),
                $code,
            );
        }

        return $code;
    }
}
