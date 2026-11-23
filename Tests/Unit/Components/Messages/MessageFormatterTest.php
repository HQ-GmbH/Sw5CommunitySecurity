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

namespace Sw5CommunitySecurity\Tests\Unit\Components\Messages;

use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Messages\DefaultMessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;

final class MessageFormatterTest extends TestCase
{
    public function testDefaultIsUsedWithoutCatalogEntry(): void
    {
        $formatter = new MessageFormatter(new DefaultMessageCatalog());

        static::assertSame('Datei: a.phar', $formatter->format('key', 'Datei: [files]', ['files' => 'a.phar']));
    }

    public function testCatalogTextAndPlaceholderValuesAreEscaped(): void
    {
        $formatter = new MessageFormatter($this->catalog(['key' => '<b>[files]</b>']));

        static::assertSame(
            '&lt;b&gt;&lt;img src=x onerror=alert(1)&gt;.phar&lt;/b&gt;',
            $formatter->format('key', '[files]', ['files' => '<img src=x onerror=alert(1)>.phar']),
        );
    }

    public function testPreEscapedValuesAreInsertedAsGiven(): void
    {
        $formatter = new MessageFormatter($this->catalog(['key' => 'A & [details]']));

        static::assertSame('A &amp; x &amp; y', $formatter->formatWithEscapedValues('key', '[details]', ['details' => 'x &amp; y']));
    }

    public function testTemplateGuardUsesCatalog(): void
    {
        $guard = new TemplateGuard(new TemplateInspector(), new MessageFormatter($this->catalog([
            'template_rejected' => 'Abgelehnt ([details]).',
            'rule_tag' => '[tag]',
        ])));

        static::assertSame(
            'Abgelehnt (content: {fetch}).',
            $guard->buildMessage(['content' => [new Finding(Finding::RULE_FETCH, '{fetch')]]),
        );
    }

    public function testFieldNamesInMessagesAreEscaped(): void
    {
        $guard = new TemplateGuard(new TemplateInspector());

        $message = $guard->buildMessage(['<script>' => [new Finding(Finding::RULE_PHP, '{php')]]);

        static::assertStringContainsString('&lt;script&gt;: {php}', $message);
        static::assertStringNotContainsString('<script>', $message);
    }

    /**
     * @param array<string, string> $texts
     */
    private function catalog(array $texts): MessageCatalog
    {
        return new class($texts) implements MessageCatalog {
            /**
             * @var array<string, string>
             */
            private array $texts;

            /**
             * @param array<string, string> $texts
             */
            public function __construct(array $texts)
            {
                $this->texts = $texts;
            }

            public function get(string $key, string $default): string
            {
                return $this->texts[$key] ?? $default;
            }
        };
    }
}
