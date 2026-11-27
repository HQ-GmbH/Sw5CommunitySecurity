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

namespace Sw5CommunitySecurity\Tests\Unit\Metadata;

use PHPUnit\Framework\TestCase;

/**
 * The editor sanitiser (backend/sw5cs_content_guard/index/header.tpl) repeats
 * the rules of HtmlInspector in JavaScript; these checks keep both in step.
 */
final class ContentGuardEditorScriptTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../Resources/views/backend/sw5cs_content_guard/index/header.tpl';

    public function testScriptAddressesRequireTheColonLikeTheServerCheck(): void
    {
        $script = $this->script();

        static::assertStringContainsString(
            'var SCRIPT_URL = /^(?:javascript:|vbscript:|livescript:|data:text\/html|data:application\/xhtml|data:text\/xml)/i;',
            $script,
        );
        static::assertStringContainsString("var VALUE_URL_ELEMENT = 'param';", $script);
        static::assertStringContainsString("(name !== 'value' || tagName === VALUE_URL_ELEMENT)", $script);
    }

    public function testTemplateContentIsCleaned(): void
    {
        // querySelectorAll() does not descend into <template> content.
        static::assertStringContainsString("tagName === 'template' && element.content && cleanTree(element.content)", $this->script());
    }

    public function testNoticeIsHtmlEncoded(): void
    {
        static::assertStringContainsString(
            "createGrowlMessage('', Ext.String.htmlEncode(window.sw5csEditorNotice), '')",
            $this->script(),
        );
    }

    public function testScriptIsInsideOneLiteralSection(): void
    {
        $content = $this->read();
        $start = strpos($content, '{literal}');
        $end = strpos($content, '{/literal}');
        $function = strpos($content, 'function cleanTree(');

        static::assertIsInt($start);
        static::assertIsInt($end);
        static::assertIsInt($function);
        static::assertSame(1, substr_count($content, '{literal}'));
        static::assertGreaterThan($start, $function);
        static::assertLessThan($end, $function);
    }

    private function script(): string
    {
        $content = $this->read();
        $start = strpos($content, '{literal}');
        static::assertIsInt($start);

        return substr($content, $start);
    }

    private function read(): string
    {
        $content = file_get_contents(self::FILE);
        static::assertIsString($content);

        return $content;
    }
}
