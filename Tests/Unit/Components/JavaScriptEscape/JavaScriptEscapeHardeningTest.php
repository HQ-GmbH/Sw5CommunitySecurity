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
     * @return array<mixed>
     */
    private static function evaluate(string $arrayCode): array
    {
        $value = eval('return ' . $arrayCode . ';');
        static::assertIsArray($value);

        return $value;
    }
}
