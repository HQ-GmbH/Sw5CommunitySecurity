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

namespace Sw5CommunitySecurity\Tests\Unit\Components\TemplateGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;

/**
 * All inputs are harmless markers: they only check that a construct is
 * recognised, none of them performs any action when rendered.
 */
final class TemplateInspectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'plain text' => ['Hallo {$sUser.firstname}, vielen Dank für Ihre Bestellung.'];
        yield 'shopware default mail header' => ['{include file="string:{config name=emailheaderplain}"}'];
        yield 'shopware default html footer' => ['{include file="string:{config name=emailfooterhtml}"}'];
        yield 'modifiers and loops' => ['{foreach $sOrderDetails as $item}{$item.articlename|escape} {$item.price|currency}{/foreach}'];
        yield 'css braces' => ['<style>body { color: #000; } .block { margin: 0; }</style>'];
        yield 'auto literal with whitespace' => ['{ extends } { fetch } { php }'];
        yield 'javascript fetch call' => ['<script>if (x) {fetch(url)}</script>'];
        yield 'valid function name' => ['{function name=row}<td>{$value}</td>{/function}'];
        yield 'valid quoted function name' => ['{function name="price_row" level=0}x{/function}'];
        yield 'valid function shorthand' => ['{function row}x{/function}'];
        yield 'valid block name' => ['{block name="frontend_mail_content"}x{/block}'];
        yield 'valid block name with dots and dashes' => ['{block name=mail.content-main}x{/block}'];
        yield 'words that only start like tags' => ['{extendsInfo} {fetchAll} {blockquote} {functions}'];
    }

    #[DataProvider('allowedProvider')]
    public function testAllowedTemplatesHaveNoFindings(string $template): void
    {
        self::assertSame([], (new TemplateInspector())->inspect($template));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function disallowedProvider(): iterable
    {
        yield 'extends' => ['{extends file="parent.tpl"}', Finding::RULE_EXTENDS];
        yield 'extends uppercase' => ['{EXTENDS file="parent.tpl"}', Finding::RULE_EXTENDS];
        yield 'extends without attributes' => ['{extends}', Finding::RULE_EXTENDS];
        yield 'fetch' => ['{fetch file="example.txt"}', Finding::RULE_FETCH];
        yield 'fetch across lines' => ["{fetch\n file=\"example.txt\"}", Finding::RULE_FETCH];
        yield 'php' => ['{php}{/php}', Finding::RULE_PHP];
        yield 'include_php' => ['{include_php file="example.php"}', Finding::RULE_PHP];
        yield 'template_object' => ['{$smarty.template_object}', Finding::RULE_TEMPLATE_OBJECT];
        yield 'template_object with spaces' => ['{$smarty . template_object}', Finding::RULE_TEMPLATE_OBJECT];
        yield 'function name with space' => ['{function name="a b"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name with quote' => ['{function name="a\'b"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name from variable' => ['{function name=$name}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name starting with digit' => ['{function name=1row}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function without name' => ['{function}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name unterminated quote' => ['{function name="row}x', Finding::RULE_FUNCTION_NAME];
        yield 'block name with quote' => ['{block name="a\'b"}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'block name with brace' => ['{block name="a{b}"}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'inside literal is still reported' => ['{literal}{fetch file="example.txt"}{/literal}', Finding::RULE_FETCH];
        yield 'inside comment is still reported' => ['{* {extends file="parent.tpl"} *}', Finding::RULE_EXTENDS];
    }

    #[DataProvider('disallowedProvider')]
    public function testDisallowedConstructsAreReported(string $template, string $expectedRule): void
    {
        $findings = (new TemplateInspector())->inspect($template);

        self::assertNotSame([], $findings);
        self::assertSame($expectedRule, $findings[0]->rule);
    }

    public function testEveryOccurrenceIsReported(): void
    {
        $findings = (new TemplateInspector())->inspect('{fetch file="a.txt"} text {extends file="b.tpl"} {fetch file="c.txt"}');

        self::assertSame(
            [Finding::RULE_FETCH, Finding::RULE_EXTENDS, Finding::RULE_FETCH],
            array_map(static fn (Finding $finding): string => $finding->rule, $findings)
        );
    }

    public function testSnippetIsShortened(): void
    {
        $findings = (new TemplateInspector())->inspect('{fetch file="' . str_repeat('a', 200) . '"}');

        self::assertLessThanOrEqual(80, mb_strlen($findings[0]->tag));
    }
}
