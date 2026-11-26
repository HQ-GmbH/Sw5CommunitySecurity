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

namespace Sw5CommunitySecurity\Tests\Unit\Components\ContentGuard;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\ContentGuard\ContentGuard;
use Sw5CommunitySecurity\Components\ContentGuard\HtmlInspector;
use Sw5CommunitySecurity\Components\ContentGuard\UnsafeContentException;

final class ContentGuardTest extends TestCase
{
    public function testSafeFieldsPass(): void
    {
        $guard = new ContentGuard(new HtmlInspector());

        static::assertSame([], $guard->findViolations(['html' => '<p><a href="/agb">AGB</a></p>', 'count' => 3, 'empty' => '', 'none' => null]));
        $guard->assertSafe(['descriptionLong' => '<p>Text</p>']);
    }

    public function testNestedFieldsAreNamedByPath(): void
    {
        $violations = (new ContentGuard(new HtmlInspector()))->findViolations([
            'txtlangbeschreibung' => '<img src=x onerror=sw5csMarker()>',
            'data' => ['2' => ['html' => '<iframe src="javascript:sw5csMarker()"></iframe>', 'title' => 'ok']],
        ]);

        static::assertSame([
            'txtlangbeschreibung' => [HtmlInspector::RULE_EVENT_HANDLER],
            'data.2.html' => [HtmlInspector::RULE_SCRIPT_URL],
        ], $violations);
    }

    public function testDeeplyNestedDataIsRejected(): void
    {
        $value = '<p>ok</p>';
        for ($i = 0; $i < ContentGuard::MAX_DEPTH; ++$i) {
            $value = [$value];
        }

        static::assertSame(['0.0.0.0.0.0.0.0' => [ContentGuard::RULE_TOO_DEEP]], (new ContentGuard(new HtmlInspector()))->findViolations([$value]));
    }

    public function testMessageNamesFieldsAndRulesWithoutContent(): void
    {
        $guard = new ContentGuard(new HtmlInspector());

        try {
            $guard->assertSafe(['html<b>' => '<script>sw5csMarker()</script><a href="javascript:sw5csMarker()">x</a>']);
            static::fail('Expected an exception.');
        } catch (UnsafeContentException $exception) {
            $message = $exception->getMessage();
        }

        static::assertStringContainsString('html&lt;b&gt;: &lt;script&gt;, Skript-Adressen (javascript: u. a.) / script addresses (javascript: etc.)', $message);
        static::assertStringStartsWith('Community Security Guard:', $message);
        static::assertStringNotContainsString('sw5csMarker', $message);
    }
}
