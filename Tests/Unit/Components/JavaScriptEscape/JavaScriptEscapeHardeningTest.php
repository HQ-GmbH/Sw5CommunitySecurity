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

namespace Sw5CommunitySecurity\Tests\Unit\Components\JavaScriptEscape;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\JavaScriptEscape\JavaScriptEscapeHardening;

/**
 * All inputs are harmless markers; only the escaping itself is checked.
 */
final class JavaScriptEscapeHardeningTest extends TestCase
{
    /**
     * Compiled code as Smarty 3.1.12 emits it for {$value|escape:'javascript'}.
     */
    private const LEGACY_COMPILED = '<?php echo strtr($_smarty_tpl->tpl_vars[\'value\']->value, '
        . JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE . ');?>';

    /**
     * @return iterable<string, array{string}>
     */
    public static function compiledValueProvider(): iterable
    {
        yield 'template variable' => ['$_smarty_tpl->tpl_vars[\'value\']->value'];
        yield 'function call' => ['trim($_smarty_tpl->tpl_vars[\'value\']->value)'];
    }

    #[DataProvider('compiledValueProvider')]
    public function testLegacyCodeMatchesTheSmartyModifierCompiler(string $value): void
    {
        static::assertSame(
            'strtr(' . $value . ', ' . JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE . ')',
            self::emitLikeSmarty3112([$value]),
        );
    }

    public function testLegacyCodeEvaluatesToTheOldReplacementList(): void
    {
        static::assertSame(
            ['\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\r" => '\\r', "\n" => '\\n', '</' => '<\\/'],
            self::evaluate(JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE),
        );
    }

    public function testGeneratedCodeEvaluatesToTheReplacementList(): void
    {
        $hardening = new JavaScriptEscapeHardening();

        static::assertSame(JavaScriptEscapeHardening::REPLACEMENTS, self::evaluate($hardening->replacementsCode()));
    }

    public function testHardenReplacesTheLegacyList(): void
    {
        $hardening = new JavaScriptEscapeHardening();
        $hardened = $hardening->harden(self::LEGACY_COMPILED);

        static::assertStringNotContainsString(JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE, $hardened);
        static::assertStringContainsString($hardening->replacementsCode(), $hardened);
        static::assertStringStartsWith('<?php echo strtr($_smarty_tpl->tpl_vars[\'value\']->value, ', $hardened);
    }

    public function testHardenReplacesEveryOccurrence(): void
    {
        $hardening = new JavaScriptEscapeHardening();
        $hardened = $hardening->harden(self::LEGACY_COMPILED . 'text' . self::LEGACY_COMPILED);

        static::assertSame(2, substr_count($hardened, $hardening->replacementsCode()));
    }

    public function testHardenIsIdempotent(): void
    {
        $hardening = new JavaScriptEscapeHardening();
        $once = $hardening->harden(self::LEGACY_COMPILED);

        static::assertSame($once, $hardening->harden($once));
    }

    public function testCodeWithoutLegacyListIsUnchanged(): void
    {
        $code = '<?php echo htmlspecialchars($_smarty_tpl->tpl_vars[\'value\']->value, ENT_QUOTES, \'UTF-8\', true);?>';

        static::assertSame($code, (new JavaScriptEscapeHardening())->harden($code));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapeProvider(): iterable
    {
        yield 'backslash' => ['a\\b', 'a\\\\b'];
        yield 'single quote' => ["a'b", "a\\'b"];
        yield 'double quote' => ['a"b', 'a\\"b'];
        yield 'line breaks' => ["a\r\nb", 'a\\r\\nb'];
        yield 'closing tag' => ['</p>', '<\\/p>'];
        yield 'comment opener' => ['<!-- marker', '<\\!-- marker'];
        yield 'lower-case <s' => ['<span>', '<\\span>'];
        yield 'upper-case <S' => ['<SPAN>', '<\\SPAN>'];
        yield 'template literal' => ['`marker`', '\\`marker\\`'];
        yield 'template placeholder' => ['${marker}', '\\$\\{marker}'];
        yield 'dollar without brace' => ['$marker', '$marker'];
        yield 'plain text' => ['sw5cs marker', 'sw5cs marker'];
    }

    #[DataProvider('escapeProvider')]
    public function testEscape(string $input, string $expected): void
    {
        static::assertSame($expected, JavaScriptEscapeHardening::escape($input));
    }

    #[DataProvider('escapeProvider')]
    public function testHardenedCompiledCodeEscapesLikeEscape(string $input, string $expected): void
    {
        $hardening = new JavaScriptEscapeHardening();

        static::assertSame($expected, strtr($input, self::evaluate($hardening->replacementsCode())));
    }

    public function testRuntimeCallWithJavaScriptTypeUsesTheCompleteList(): void
    {
        self::defineRuntimeStub();
        $hardened = (new JavaScriptEscapeHardening())->harden('<?php echo smarty_modifier_escape($_smarty_tpl->tpl_vars[\'value\']->value, $_smarty_tpl->tpl_vars[\'type\']->value);?>');

        static::assertStringNotContainsString(' smarty_modifier_escape(', $hardened);
        $value = 'sw5cs </p> <!-- <span> `tpl` ${marker}';
        static::assertSame(JavaScriptEscapeHardening::escape($value), self::runWrapped($value, 'javascript'));
    }

    public function testRuntimeCallWithOtherTypesIsPassedOn(): void
    {
        self::defineRuntimeStub();

        static::assertSame('stub:html:<b>', self::runWrapped('<b>', 'html'));
        static::assertSame('stub:url:a b', self::runWrapped('a b', 'url'));
        static::assertSame('stub:html:x', eval('return ' . (new JavaScriptEscapeHardening())->runtimeWrapperCode() . '(\'x\');'));
    }

    public function testPluginHeaderAndHardenedCodeAreLeftAlone(): void
    {
        $hardening = new JavaScriptEscapeHardening();
        $header = "<?php if (!is_callable('smarty_modifier_escape')) include '/shop/engine/Library/Smarty/plugins/modifier.escape.php';?>";

        static::assertSame($header, $hardening->harden($header));
        $once = $hardening->harden($header . '<?php echo smarty_modifier_escape($v, $t);?>');
        static::assertSame($once, $hardening->harden($once));
        static::assertSame(1, substr_count($once, 'static function'));
    }

    public function testNocacheSectionsAreHardenedWithTheSameEscaping(): void
    {
        self::defineRuntimeStub();
        $inner = '<?php echo smarty_modifier_escape($value, $type);?>';
        // As smarty_internal_templatecompilerbase.php (3.1.12) stores nocache code.
        $compiled = "<?php echo '/*%%SmartyNocache:abc%%*/" . addcslashes($inner, '\'\\') . "/*/%%SmartyNocache:abc%%*/';?>";

        $hardened = (new JavaScriptEscapeHardening())->harden($compiled);
        $innerHardened = self::unwrapNocache($hardened);

        static::assertStringNotContainsString(' smarty_modifier_escape(', $innerHardened);
        $value = "sw5cs </p> <!-- `tpl` \${x} '\"\\";
        static::assertSame(JavaScriptEscapeHardening::escape($value), self::evaluateEcho($innerHardened, $value, 'javascript'));
        static::assertSame('stub:url:' . $value, self::evaluateEcho($innerHardened, $value, 'url'));
        static::assertSame($hardened, (new JavaScriptEscapeHardening())->harden($hardened));
    }

    public function testLegacyListInNocacheSectionsIsReplaced(): void
    {
        $inner = '<?php echo strtr($value, ' . JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE . ');?>';
        $compiled = "<?php echo '/*%%SmartyNocache:abc%%*/" . addcslashes($inner, '\'\\') . "/*/%%SmartyNocache:abc%%*/';?>";

        $innerHardened = self::unwrapNocache((new JavaScriptEscapeHardening())->harden($compiled));
        $value = 'sw5cs <!-- <script `x`';

        static::assertSame(JavaScriptEscapeHardening::escape($value), self::evaluateEcho($innerHardened, $value, ''));
    }

    public function testRegisteredEscapeMethodGetsTheCompleteListForJavaScript(): void
    {
        $hardening = new JavaScriptEscapeHardening();
        $code = '<?php echo ' . JavaScriptEscapeHardening::REGISTERED_CALL_PREFIX . 'sEscapeString($value, $type);?>';
        $hardened = $hardening->harden($code);

        static::assertStringNotContainsString('->sEscapeString(', $hardened);
        static::assertSame($hardened, $hardening->harden($hardened));

        $exporter = new class {
            public function sEscapeString(string $string, string $esc_type = '', ?string $char_set = null): string
            {
                return 'export:' . $esc_type . ':' . $string;
            }
        };
        $expression = substr($hardened, \strlen('<?php echo '), -\strlen(';?>'));
        $run = static function (object $exporter, string $value, string $type, string $expression) {
            if (!class_exists('Smarty', false)) {
                // Constant used by the compiled code; Smarty itself is not loaded in unit tests.
                eval('class Smarty { const PLUGIN_MODIFIER = "modifier"; }');
            }
            $_smarty_tpl = (object) ['smarty' => (object) ['registered_plugins' => ['modifier' => ['escape' => [[$exporter, 'sEscapeString']]]]]];

            return eval('return ' . $expression . ';');
        };

        static::assertSame(JavaScriptEscapeHardening::escape('a </p> `b`'), $run($exporter, 'a </p> `b`', 'javascript', $expression));
        static::assertSame('export:csv:a;b', $run($exporter, 'a;b', 'csv', $expression));
    }

    public function testMayNeedHardening(): void
    {
        static::assertTrue(JavaScriptEscapeHardening::mayNeedHardening('x smarty_modifier_escape( y'));
        static::assertTrue(JavaScriptEscapeHardening::mayNeedHardening(JavaScriptEscapeHardening::LEGACY_REPLACEMENTS_CODE));
        static::assertFalse(JavaScriptEscapeHardening::mayNeedHardening('<?php echo htmlspecialchars($v);?>'));
    }

    /**
     * Return statement of engine/Library/Smarty/plugins/modifiercompiler.escape.php,
     * case 'javascript', copied unchanged (Shopware 5.6.10 and 5.7.20).
     * Source: Smarty 3.1.12, author Rodney Rehm, copyright New Digital Group, Inc.
     * and Uwe Tews; SPDX-License-Identifier: LGPL-3.0-or-later (THIRD-PARTY-NOTICES.md).
     *
     * @param array{string} $params
     */
    private static function emitLikeSmarty3112(array $params): string
    {
        return 'strtr(' . $params[0] . ', array("\\\\" => "\\\\\\\\", "\'" => "\\\\\'", "\"" => "\\\\\"", "\\r" => "\\\\r", "\\n" => "\\\n", "</" => "<\/" ))';
    }

    /**
     * Returns the PHP code stored in the nocache section of compiled code.
     */
    private static function unwrapNocache(string $compiled): string
    {
        static::assertSame(1, preg_match("/^<\\?php echo ('.*');\\?>$/s", $compiled, $match));
        $stored = eval('return ' . $match[1] . ';');
        static::assertIsString($stored);

        return (string) preg_replace('/\/\*\/?%%SmartyNocache:[^%]+%%\*\//', '', $stored);
    }

    private static function evaluateEcho(string $code, string $value, string $type): string
    {
        $expression = substr($code, \strlen('<?php echo '), -\strlen(';?>'));
        $result = (static function (string $value, string $type, string $expression) {
            return eval('return ' . $expression . ';');
        })($value, $type, $expression);
        static::assertIsString($result);

        return $result;
    }

    /**
     * Stand-in for Smarty's runtime function in the global namespace.
     */
    private static function defineRuntimeStub(): void
    {
        if (!\function_exists('smarty_modifier_escape')) {
            eval('function smarty_modifier_escape($string, $esc_type = "html", $char_set = null, $double_encode = true) { return "stub:" . $esc_type . ":" . $string; }');
        }
    }

    private static function runWrapped(string $value, string $type): string
    {
        $code = (new JavaScriptEscapeHardening())->harden('<?php echo smarty_modifier_escape($value, $type);?>');
        $expression = substr($code, \strlen('<?php echo '), -\strlen(';?>'));
        $result = (static function (string $value, string $type, string $expression) {
            return eval('return ' . $expression . ';');
        })($value, $type, $expression);
        static::assertIsString($result);

        return $result;
    }

    /**
     * @return array<mixed>
     */
    private static function evaluate(string $arrayCode): array
    {
        $value = eval('return ' . $arrayCode . ';');
        static::assertIsArray($value);

        return $value;
    }
}
