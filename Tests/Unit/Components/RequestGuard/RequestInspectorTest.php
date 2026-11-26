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

namespace Sw5CommunitySecurity\Tests\Unit\Components\RequestGuard;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\RequestGuard\RequestInspector;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;

/**
 * Only harmless markers are used; nothing here is a working attack.
 */
final class RequestInspectorTest extends TestCase
{
    public function testOrdinaryInputIsKept(): void
    {
        $input = [
            'sSearch' => 'Schrauben {M6} 10 Stück',
            'json' => '{"items":[{"id":1}]}',
            'object' => '{a:1, b:{c:2}}',
            'variable' => 'Hallo {$sUserData.billingaddress.firstname}',
            'math' => '{math equation="x + y" x=1 y=2}',
            'number' => 5,
            'nested' => ['street' => 'Musterweg 1', 'list' => ['a', 'b']],
            'empty' => null,
        ];

        [$cleaned, $removed] = $this->inspector()->clean($input, 'post');

        static::assertSame($input, $cleaned);
        static::assertSame([], $removed);
    }

    public function testValuesWithSmartyConstructsAreCleared(): void
    {
        [$cleaned, $removed] = $this->inspector()->clean([
            'firstname' => '{fetch file="sw5cs-marker.txt"}',
            'lastname' => 'Muster',
            'address' => ['street' => '{$smarty.template_object}', 'city' => 'Berlin'],
            'comment' => '{Sw5csMarker::VALUE}',
            'template' => '{extends file="sw5cs-marker.tpl"}',
        ], 'post');

        static::assertSame([
            'firstname' => null,
            'lastname' => 'Muster',
            'address' => ['street' => null, 'city' => 'Berlin'],
            'comment' => null,
            'template' => null,
        ], $cleaned);
        static::assertSame([
            'post.firstname' => [Finding::RULE_FETCH],
            'post.address.street' => [Finding::RULE_TEMPLATE_OBJECT],
            'post.comment' => [Finding::RULE_STATIC_CLASS],
            'post.template' => [Finding::RULE_EXTENDS],
        ], $removed);
    }

    public function testKeysWithSmartyConstructsAreRemovedWithoutNamingThem(): void
    {
        [$cleaned, $removed] = $this->inspector()->clean(['{php}sw5cs{/php}' => 'x', 'ok' => 'y'], 'query');

        static::assertSame(['ok' => 'y'], $cleaned);
        static::assertSame(['query.[key]' => [Finding::RULE_PHP]], $removed);
    }

    public function testDeeplyNestedInputIsCleared(): void
    {
        $value = 'x';
        for ($i = 0; $i < RequestInspector::MAX_DEPTH; ++$i) {
            $value = [$value];
        }

        [$cleaned, $removed] = $this->inspector()->clean(['deep' => $value]);

        static::assertContains([RequestInspector::RULE_TOO_DEEP], $removed);
        static::assertNotSame(['deep' => $value], $cleaned);
    }

    private function inspector(): RequestInspector
    {
        return new RequestInspector(new TemplateInspector());
    }
}
