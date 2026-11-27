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
 * A modifier "escape" registered by Shopware or a plugin (e.g. the product
 * export, engine/Shopware/Core/sExport.php, sEscapeString) is compiled to a
 * direct call of the registered function or method. Such calls are wrapped so
 * that the registered function is still called unchanged; only for
 * "javascript" its result is additionally escaped with the replacements Smarty
 * added in 3.1.48 (EXTRA_REPLACEMENTS). For a result of the old list this
 * equals the complete list; a different own escaping is kept.
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
     * Replacements added in Smarty 3.1.48; applied to the result of a
     * registered escape function for "javascript".
     */
    public const EXTRA_REPLACEMENTS = [
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
     * @var array{prefix: string, callable: string}|null
     */
    private ?array $registeredCallPrefix = null;

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

    /**
     * @param mixed $registeredEscape callable registered as modifier "escape" for the template, if any
     */
    public function harden(string $compiledCode, $registeredEscape = null): string
    {
        $this->registeredCallPrefix = self::registeredCallPrefix($registeredEscape);

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
     * Immediately invoked closure taking the place of a call of the registered
     * escape function: calls it unchanged with all arguments and, for
     * "javascript", escapes its result additionally (EXTRA_REPLACEMENTS).
     *
     * @param string $callableCode PHP expression of the registered callable
     */
    public function registeredWrapperCode(string $callableCode): string
    {
        return '(static function ($callable, ...$args) {'
            . ' $result = \call_user_func_array($callable, $args);'
            . ' return isset($args[1]) && $args[1] === \'javascript\' && \is_string($result) ? strtr($result, ' . self::arrayCode(self::EXTRA_REPLACEMENTS) . ') : $result; })'
            . '(' . $callableCode . ', ';
    }

    /**
     * How Smarty 3.1.12 compiles a call of the registered escape function
     * (smarty_internal_compile_private_modifier.php) and the PHP expression of
     * that callable, or null if there is nothing to wrap.
     *
     * @param mixed $registered
     *
     * @return array{prefix: string, callable: string}|null
     */
    public static function registeredCallPrefix($registered): ?array
    {
        $name = '/^\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/';

        // A static method registered as string ('Class::method') is compiled
        // like the array form: "{$function}(...)" yields Class::method(...)
        // (smarty_internal_compile_private_modifier.php, case 1).
        if (\is_string($registered) && substr_count($registered, '::') === 1) {
            return self::registeredCallPrefix(explode('::', $registered, 2));
        }

        if (\is_string($registered) && preg_match($name, $registered) === 1 && $registered !== self::RUNTIME_FUNCTION) {
            return ['prefix' => $registered . '(', 'callable' => var_export($registered, true)];
        }

        if (\is_array($registered) && isset($registered[0], $registered[1]) && \is_string($registered[1]) && preg_match($name, $registered[1]) === 1) {
            if (\is_object($registered[0])) {
                return ['prefix' => self::REGISTERED_CALL_PREFIX . $registered[1] . '(', 'callable' => '[' . rtrim(self::REGISTERED_CALL_PREFIX, '->') . ', ' . var_export($registered[1], true) . ']'];
            }
            if (\is_string($registered[0]) && preg_match($name, $registered[0]) === 1) {
                return ['prefix' => $registered[0] . '::' . $registered[1] . '(', 'callable' => var_export([$registered[0], $registered[1]], true)];
            }
        }

        return null;
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
            $this->replacementsCode = self::arrayCode(self::REPLACEMENTS);
        }

        return $this->replacementsCode;
    }

    /**
     * @param array<string, string> $replacements
     */
    private static function arrayCode(array $replacements): string
    {
        $entries = [];
        foreach ($replacements as $search => $replace) {
            $entries[] = var_export($search, true) . ' => ' . var_export($replace, true);
        }

        return 'array(' . implode(', ', $entries) . ')';
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

        // Without the template (e.g. called directly), calls of a registered
        // escape object method are still recognised by their compiled form.
        $objectPrefix = $quote(self::REGISTERED_CALL_PREFIX);
        if ($this->registeredCallPrefix === null && strpos($code, $objectPrefix) !== false) {
            $code = (string) preg_replace_callback(
                '/' . preg_quote($objectPrefix, '/') . '(\w+)\s*\(/',
                fn (array $match): string => $quote($this->registeredWrapperCode('[' . rtrim(self::REGISTERED_CALL_PREFIX, '->') . ', ' . var_export($match[1], true) . ']')),
                $code,
            );
        }

        $registered = $this->registeredCallPrefix;
        if ($registered !== null && strpos($code, $quote($registered['prefix'])) !== false) {
            $wrapper = $quote($this->registeredWrapperCode($registered['callable']));
            $code = (string) preg_replace_callback(
                '/(?<![\w$>:\\\\\'"])' . preg_quote($quote($registered['prefix']), '/') . '/',
                static fn (): string => $wrapper,
                $code,
            );
        }

        return $code;
    }
}
