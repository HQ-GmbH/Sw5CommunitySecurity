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
use Sw5CommunitySecurity\Components\ContentGuard\StoredContentScanner;
use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\Messages\DefaultMessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;

final class StoredContentScannerTest extends TestCase
{
    private const SNIPPET_FILE = __DIR__ . '/../../../../Resources/snippets/backend/sw5_community_security/messages.ini';

    public function testLabelsUseEnglishDefaults(): void
    {
        $results = $this->scanner(new DefaultMessageCatalog())->scan();

        static::assertSame([
            ['source' => 'Shop page', 'record' => '7 / Imprint', 'field' => 'html', 'rules' => [HtmlInspector::RULE_EVENT_HANDLER]],
            ['source' => 'Translation', 'record' => 'page / key 7 / language 2', 'field' => 'html', 'rules' => [HtmlInspector::RULE_SCRIPT_URL]],
        ], $results);
    }

    public function testLabelsComeFromTheSnippets(): void
    {
        $snippets = parse_ini_file(self::SNIPPET_FILE, true, \INI_SCANNER_RAW);
        static::assertIsArray($snippets);
        $german = $snippets['de_DE'] ?? null;
        static::assertIsArray($german);

        $catalog = new class($german) implements MessageCatalog {
            /**
             * @var array<array-key, mixed>
             */
            private array $texts;

            /**
             * @param array<array-key, mixed> $texts
             */
            public function __construct(array $texts)
            {
                $this->texts = $texts;
            }

            public function get(string $key, string $default): string
            {
                $text = $this->texts[$key] ?? null;

                return \is_string($text) ? $text : $default;
            }
        };

        $results = $this->scanner($catalog)->scan();

        static::assertSame(['Shopseite', 'Übersetzung'], array_column($results, 'source'));
        static::assertSame(['7 / Imprint', 'page / Schlüssel 7 / Sprache 2'], array_column($results, 'record'));
    }

    private function scanner(MessageCatalog $catalog): StoredContentScanner
    {
        $rows = new class implements RowReader {
            public function fetchAll(string $sql, array $params = [], array $types = []): array
            {
                if (($params[0] ?? null) !== 0) {
                    return [];
                }
                if (str_contains($sql, 'FROM s_cms_static')) {
                    return [['id' => '7', 'description' => 'Imprint', 'html' => '<img src="x" onerror="sw5csMarker()">']];
                }
                if (str_contains($sql, 'FROM s_core_translations')) {
                    return [[
                        'id' => '3',
                        'objecttype' => 'page',
                        'objectkey' => '7',
                        'objectlanguage' => '2',
                        'objectdata' => serialize(['html' => '<a href="javascript:sw5csMarker()">x</a>']),
                    ]];
                }

                return [];
            }
        };

        return new StoredContentScanner($rows, new ContentGuard(new HtmlInspector()), new MessageFormatter($catalog));
    }
}
