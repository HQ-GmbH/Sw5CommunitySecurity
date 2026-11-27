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
use Sw5CommunitySecurity\Components\TemplateGuard\SnippetImportValues;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;

final class SnippetImportValuesTest extends TestCase
{
    public function testEntityEncodedConstructIsInspectedAsStored(): void
    {
        // Entities end with ";", the delimiter, so the field is quoted.
        $csv = "namespace;name;value-de_DE-1\nsw5cs/test;marker;\"&#123;fetch file='marker.txt'&#125;\"\n";
        $values = SnippetImportValues::fromFile($csv, 'snippets.csv');

        static::assertContains("{fetch file='marker.txt'}", $values);
        static::assertNotSame([], (new TemplateGuard(new TemplateInspector()))->findViolations($values));
    }

    public function testEachCellIsInspectedOnItsOwn(): void
    {
        // An apostrophe in one row must not hide a construct in the next row.
        $csv = "namespace;name;value-de_DE-1\nsw5cs/test;a;{\$a|default:'\nsw5cs/test;b;{Marker::NAME}'\n";
        $values = SnippetImportValues::fromFile($csv, 'snippets.csv');

        static::assertContains("{Marker::NAME}'", $values);
        static::assertNotSame([], (new TemplateGuard(new TemplateInspector()))->findViolations($values));
    }

    public function testLeadingApostropheIsRemovedAndQuotedFieldsAreRead(): void
    {
        $csv = "namespace;name;value-de_DE-1\nsw5cs/test;quoted;\"'Text; mit \"\"Anführung\"\"\"\n";
        $values = SnippetImportValues::fromFile($csv, 'snippets.csv');

        static::assertContains('Text; mit "Anführung"', $values);
    }

    public function testXmlValuesAreDecoded(): void
    {
        $xml = '<?xml version="1.0"?><Workbook><Worksheet><Table><Row><Cell><Data>&amp;#123;fetch file="marker.txt"&amp;#125;</Data></Cell></Row></Table></Worksheet></Workbook>';
        $values = SnippetImportValues::fromFile($xml, 'snippets.xml');

        static::assertContains('{fetch file="marker.txt"}', $values);
    }

    public function testPlainSnippetsStayUnchanged(): void
    {
        $values = SnippetImportValues::fromFile("namespace;name;value-de_DE-1\nfrontend/x;y;Hallo {\$sName}\n", 'snippets.csv');

        static::assertSame([], (new TemplateGuard(new TemplateInspector()))->findViolations($values));
    }
}
