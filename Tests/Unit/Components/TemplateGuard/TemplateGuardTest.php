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

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;
use Sw5CommunitySecurity\Components\TemplateGuard\UnsafeTemplateException;

final class TemplateGuardTest extends TestCase
{
    private TemplateGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new TemplateGuard(new TemplateInspector());
    }

    public function testSafeFieldsPass(): void
    {
        $this->guard->assertSafe([
            'subject' => 'Ihre Bestellung {$sOrderNumber}',
            'content' => '{include file="string:{config name=emailheaderplain}"}',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testNonStringValuesAreIgnored(): void
    {
        self::assertSame([], $this->guard->findViolations(['id' => 5, 'flags' => ['x', true], 'empty' => '', 'none' => null]));
    }

    public function testViolationsAreGroupedByField(): void
    {
        $violations = $this->guard->findViolations([
            'subject' => 'ok',
            'content' => '{fetch file="a.txt"}',
            'contentHtml' => '{extends file="b.tpl"}',
        ]);

        self::assertSame(['content', 'contentHtml'], array_keys($violations));
    }

    public function testUnsafeFieldsThrowWithFieldAndRuleButWithoutContent(): void
    {
        try {
            $this->guard->assertSafe(['content' => '{fetch file="secret-marker.txt"}']);
            self::fail('Expected UnsafeTemplateException');
        } catch (UnsafeTemplateException $exception) {
            self::assertStringContainsString('content: {fetch}', $exception->getMessage());
            self::assertStringNotContainsString('secret-marker', $exception->getMessage());
        }
    }

    public function testNestedArraysAreInspectedWithDottedPaths(): void
    {
        $violations = $this->guard->findViolations([
            'subject' => 'ok',
            'containers' => [
                ['description' => 'ok', 'text' => ['content' => '{fetch file="marker.txt"}']],
                ['description' => '{extends file="marker.tpl"}'],
            ],
            'id' => 7,
        ]);

        self::assertSame(['containers.0.text.content', 'containers.1.description'], array_keys($violations));
    }

    public function testTooDeeplyNestedDataIsRejected(): void
    {
        $data = 'ok';
        for ($i = 0; $i < TemplateGuard::MAX_DEPTH + 1; ++$i) {
            $data = ['x' => $data];
        }

        $violations = $this->guard->findViolations(['data' => $data]);

        self::assertCount(1, $violations);
        self::assertSame(Finding::RULE_TOO_DEEP, array_values($violations)[0][0]->rule);
    }

    public function testRelaxedModeIsPassedThrough(): void
    {
        $fields = ['content' => '{include file=$marker}'];

        self::assertSame([], $this->guard->findViolations($fields, false));
        self::assertSame(['content'], array_keys($this->guard->findViolations($fields)));
    }

    public function testFieldNamesInMessageAreEscaped(): void
    {
        $message = $this->guard->buildMessage(['<b>x</b>' => (new TemplateInspector())->inspect('{fetch file="a"}')]);

        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $message);
        self::assertStringNotContainsString('<b>', $message);
    }
}
