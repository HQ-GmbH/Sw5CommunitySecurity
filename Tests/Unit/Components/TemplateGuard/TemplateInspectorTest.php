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
        yield 'valid quoted block name with dots and dashes' => ['{block name="mail.content-main"}x{/block}'];
        yield 'block with flag after the name' => ['{block name="frontend_mail_content" append}x{/block}'];
        yield 'function with nocache flag' => ['{function name=row nocache}x{/function}'];
        yield 'include shorthand literal' => ["{include 'frontend/_includes/marker.tpl'}"];
        yield 'include with whitespace before the end' => ['{include file="frontend/_includes/marker.tpl" }'];
        yield 'include with further named attributes' => ['{include file="frontend/_includes/marker.tpl" points=$marker.points type="aggregated"}'];
        yield 'javascript object keys named like tags' => ['<script>var m = {include:"marker"}, n = {block:1}, o = {Function=1};</script>'];
        yield 'apostrophe inside double-quoted string' => ["{\$marker|default:\"it's\"} {\$marker|default:\"it's\"}"];
        yield 'words that only start like tags' => ['{extendsInfo} {fetchAll} {blockquote} {functions} {includes} {mathematics}'];
        yield 'include theme path' => ['{include file="frontend/_includes/marker.tpl"}'];
        yield 'include single quoted path with variables passed' => ["{include file='frontend/detail/marker.tpl' sArticle=\$sArticle}"];
        yield 'include config with quoted name' => ['{include file="string:{config name="mainfeatures"}"}'];
        yield 'include payment description (shopware default)' => ['{include file="string:{$additional.payment.additionaldescription}"}'];
        yield 'include country notice (shopware default)' => ["{include file='string:{\$sCountry.notice}'}"];
        yield 'math literal equation' => ['{math equation="x + y * 2" x=$a y=$b}'];
        yield 'css pseudo element outside tags' => ['<style>.btn::before { content: ""; } a::after{color:red}</style>'];
        yield 'double colon in auto literal braces' => ['<script>var x = { a: "b::c" };</script>'];
        yield 'double colon in single-quoted string' => ["{s name='sw5cs::marker'}Text{/s}"];
        yield 'double colon in comment' => ['{* Sw5csMarker::VALUE *}'];
        yield 'math with functions and parentheses' => ['{math equation="round(x / 3, 2)" x=$total format="%.2f"}'];
    }

    #[DataProvider('allowedProvider')]
    public function testAllowedTemplatesHaveNoFindings(string $template): void
    {
        static::assertSame([], (new TemplateInspector())->inspect($template));
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
        yield 'include file from variable' => ['{include file=$marker}', Finding::RULE_INCLUDE_FILE];
        yield 'include unquoted path' => ['{include file=marker.tpl}', Finding::RULE_INCLUDE_FILE];
        yield 'include parent directory' => ['{include file="../marker.tpl"}', Finding::RULE_INCLUDE_FILE];
        yield 'include absolute path' => ['{include file="file:/marker.tpl"}', Finding::RULE_INCLUDE_FILE];
        yield 'include with backtick variable' => ['{include file="string:`$marker`"}', Finding::RULE_INCLUDE_FILE];
        yield 'include string with modifier' => ['{include file="string:{$marker|lower}"}', Finding::RULE_INCLUDE_FILE];
        yield 'include string literal with tag' => ['{include file="string:{marker}"}', Finding::RULE_INCLUDE_FILE];
        yield 'include with quote in path' => ['{include file="mark\'er.tpl"}', Finding::RULE_INCLUDE_FILE];
        yield 'include with escaped quote' => ['{include file="mark\\"er.tpl"}', Finding::RULE_INCLUDE_FILE];
        yield 'include file given twice' => ['{include file="a.tpl" file="b.tpl"}', Finding::RULE_INCLUDE_FILE];
        yield 'include without file' => ['{include}', Finding::RULE_INCLUDE_FILE];
        yield 'math equation from variable' => ['{math equation=$marker}', Finding::RULE_MATH_EQUATION];
        yield 'math equation with quote' => ['{math equation="x\'y" x=1}', Finding::RULE_MATH_EQUATION];
        yield 'math equation with variable inside' => ['{math equation="x + $marker" x=1}', Finding::RULE_MATH_EQUATION];
        yield 'math equation with backtick' => ['{math equation="`marker`"}', Finding::RULE_MATH_EQUATION];
        yield 'math without equation' => ['{math x=1}', Finding::RULE_MATH_EQUATION];
        yield 'static class constant' => ['{Sw5csMarker::VALUE}', Finding::RULE_STATIC_CLASS];
        yield 'static class method in if' => ['{if Sw5csMarker::check()}x{/if}', Finding::RULE_STATIC_CLASS];
        yield 'namespaced static class' => ['{assign var="x" value=\\Sw5cs\\Marker::VALUE}', Finding::RULE_STATIC_CLASS];
        yield 'static property' => ['{$x = Sw5csMarker::$value}', Finding::RULE_STATIC_CLASS];
        yield 'static class in double-quoted string' => ['{"x"|cat:"{Sw5csMarker::VALUE}"}', Finding::RULE_STATIC_CLASS];
        yield 'static class between apostrophes in double-quoted strings' => ["{\"'\"|cat:Sw5csMarker::value()|cat:\"'\"}", Finding::RULE_STATIC_CLASS];
        yield 'static class after a closed literal section' => ["{literal}{'{/literal}{Sw5csMarker::value()}{literal}'}{/literal}", Finding::RULE_STATIC_CLASS];
        yield 'static class after a literal tag inside a comment' => ['{* {literal} *}{Sw5csMarker::value()}{* {/literal} *}', Finding::RULE_STATIC_CLASS];
        yield 'static class after brace with nul byte' => ["{\0'{Sw5csMarker::value()}'}", Finding::RULE_STATIC_CLASS];
        yield 'static class after backtick' => ["{marker`'{Sw5csMarker::value()}'}", Finding::RULE_STATIC_CLASS];
        yield 'static class after escaped single quote' => ["{'\\''|cat:Sw5csMarker::value()}", Finding::RULE_STATIC_CLASS];
        yield 'static class after escaped double quote' => ['{"\\""|cat:Sw5csMarker::value()}', Finding::RULE_STATIC_CLASS];
        yield 'insert' => ['{insert name="marker"}', TemplateInspector::RULE_INSERT];
        yield 'insert with modifier' => ['{insert|lower name="marker"}', TemplateInspector::RULE_INSERT];
        yield 'fetch with modifier' => ['{fetch|lower file="marker.txt"}', Finding::RULE_FETCH];
        yield 'extends with modifier' => ['{extends|lower file="marker.tpl"}', Finding::RULE_EXTENDS];
        yield 'php with modifier' => ['{php|lower}{/php}', Finding::RULE_PHP];
        yield 'include with modifier' => ['{include|lower file=$marker}', Finding::RULE_INCLUDE_FILE];
        yield 'math with modifier' => ['{math|lower equation=$marker}', Finding::RULE_MATH_EQUATION];
        yield 'function with modifier' => ['{function|lower name="a b"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'block with modifier' => ['{block|lower name="a b"}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'include literal continued by modifier' => ['{include file="marker.tpl"|cat:$marker}', Finding::RULE_INCLUDE_FILE];
        yield 'include literal continued by operator' => ['{include file="marker.tpl" + $marker}', Finding::RULE_INCLUDE_FILE];
        yield 'include shorthand from variable' => ['{include $marker}', Finding::RULE_INCLUDE_FILE];
        yield 'include shorthand continued by modifier' => ['{include "marker.tpl"|cat:$marker}', Finding::RULE_INCLUDE_FILE];
        yield 'include shorthand with decoy file in other attribute' => ["{include \$marker a=\" file='marker.tpl'\"}", Finding::RULE_INCLUDE_FILE];
        yield 'include shorthand and file attribute' => ['{include "marker.tpl" file=$marker}', Finding::RULE_INCLUDE_FILE];
        yield 'math literal continued by modifier' => ['{math equation="1"|cat:$marker}', Finding::RULE_MATH_EQUATION];
        yield 'math with decoy equation in other attribute' => ["{math equation=\$marker a=\" equation='1'\"}", Finding::RULE_MATH_EQUATION];
        yield 'function name given twice' => ['{function name=row name="a b"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name continued by modifier' => ['{function name="row"|cat:"marker"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function unquoted name continued by modifier' => ['{function name=row|cat:"marker"}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name continued by operator' => ['{function name=row + 1}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'function name continued by word operator' => ['{function name=row eq 1}x{/function}', Finding::RULE_FUNCTION_NAME];
        yield 'block name given twice' => ['{block name=row name="a b"}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'block name continued by modifier' => ['{block name="row"|cat:$marker}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'block unquoted name as expression' => ['{block name=mail.content-main}x{/block}', Finding::RULE_BLOCK_NAME];
        yield 'template_object in single-quoted index' => ["{\$smarty['template_object']}", Finding::RULE_TEMPLATE_OBJECT];
        yield 'template_object in double-quoted index' => ['{$smarty["template_object"]}', Finding::RULE_TEMPLATE_OBJECT];
        yield 'template_object in unquoted index' => ['{$smarty[template_object]}', Finding::RULE_TEMPLATE_OBJECT];
        yield 'template_object in brace index' => ["{\$smarty.{'template_object'}}", Finding::RULE_TEMPLATE_OBJECT];
    }

    #[DataProvider('disallowedProvider')]
    public function testDisallowedConstructsAreReported(string $template, string $expectedRule): void
    {
        $findings = (new TemplateInspector())->inspect($template);

        static::assertNotSame([], $findings);
        static::assertSame($expectedRule, $findings[0]->rule);
    }

    public function testEveryOccurrenceIsReported(): void
    {
        $findings = (new TemplateInspector())->inspect('{fetch file="a.txt"} text {extends file="b.tpl"} {fetch file="c.txt"}');

        static::assertSame(
            [Finding::RULE_FETCH, Finding::RULE_EXTENDS, Finding::RULE_FETCH],
            array_map(static fn (Finding $finding): string => $finding->rule, $findings),
        );
    }

    public function testRelaxedModeSkipsIncludeAndMathOnly(): void
    {
        $template = '{include file=$marker} {math equation=$marker} {fetch file="marker.txt"}';
        $inspector = new TemplateInspector();

        static::assertSame(
            [Finding::RULE_FETCH],
            array_map(static fn (Finding $finding): string => $finding->rule, $inspector->inspect($template, false)),
        );
        static::assertCount(3, $inspector->inspect($template));
    }

    public function testDeeplyNestedAndUnclosedInputIsInspectedInReasonableTime(): void
    {
        $started = microtime(true);
        $template = str_repeat('{"', 100000) . str_repeat('{literal}{*', 50000) . '{Sw5csMarker::value()}';
        $findings = (new TemplateInspector())->inspect($template);

        static::assertContains(Finding::RULE_STATIC_CLASS, array_map(static fn (Finding $finding): string => $finding->rule, $findings));
        static::assertLessThan(5.0, microtime(true) - $started);
    }

    public function testManyNestedListedTagsAreReportedAsTooLarge(): void
    {
        $started = microtime(true);
        $template = str_repeat('{include file="marker.tpl" a="', 30000);
        $rules = array_map(static fn (Finding $finding): string => $finding->rule, (new TemplateInspector())->inspect($template));

        static::assertContains(Finding::RULE_TOO_LARGE, $rules);
        static::assertLessThan(5.0, microtime(true) - $started);
    }

    public function testSnippetIsShortened(): void
    {
        $findings = (new TemplateInspector())->inspect('{fetch file="' . str_repeat('a', 200) . '"}');

        static::assertNotSame([], $findings);
        foreach ($findings as $finding) {
            static::assertLessThanOrEqual(80, mb_strlen($finding->tag));
        }
    }
}
