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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\ContentGuard\HtmlInspector;

/**
 * Only harmless markers (sw5csMarker) are used; nothing here is a working attack.
 */
final class HtmlInspectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedContent(): iterable
    {
        yield 'plain text' => ['Lieferung in 2–3 Tagen, onclick= ist hier nur Text.'];
        yield 'text with comparison' => ['Preis < 10 € onload=1'];
        yield 'formatting' => ['<p><strong>Fett</strong> <em>kursiv</em> <span style="color: #c00;">rot</span></p>'];
        yield 'links' => ['<a href="https://example.org/agb" target="_blank" title="AGB">AGB</a> <a href="mailto:info@example.org">Mail</a> <a href="/kontakt">Kontakt</a> <a href="#oben">oben</a>'];
        yield 'images' => ['<img src="media/image/bild.jpg" alt="Bild" width="300"> <img src="data:image/png;base64,iVBORw0KGgo=" alt="">'];
        yield 'table' => ['<table border="1"><tr><td colspan="2">Zelle</td></tr></table>'];
        yield 'video iframe' => ['<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" frameborder="0" allowfullscreen></iframe>'];
        yield 'video and audio without handlers' => ['<video src="media/video/film.mp4" controls poster="media/image/vorschau.jpg"></video><audio src="media/audio/ton.mp3" controls></audio>'];
        yield 'embed with normal address' => ['<embed src="media/pdf/katalog.pdf" type="application/pdf" width="600" height="400">'];
        yield 'data attributes and words starting with on' => ['<div data-onclick="x" class="online one" title="onmouseover=foo">Text</div>'];
        yield 'link text mentioning javascript' => ['<p>Siehe <a href="https://example.org/javascript-tipps">javascript: Tipps</a></p>'];
        yield 'relative link starting with javascript' => ['<a href="javascript-tutorial.html">Tutorial</a> <a href="vbscript-intro.html">Intro</a>'];
        yield 'option value javascript' => ['<select name="lang"><option value="javascript">JavaScript</option><option value="javascript:">x</option></select>'];
        yield 'input value with scheme' => ['<input type="text" value="javascript:sw5csMarker()">'];
        yield 'quoted greater-than in ordinary attributes' => ['<p title="a > b" class="online">Text</p><img alt="1 > 0" src="media/image/bild.jpg">'];
        yield 'escaped markup shown as text' => ['<pre>&lt;a href=&quot;javascript:sw5csMarker()&quot; onclick=&quot;x&quot;&gt;</pre>'];
        yield 'deep but allowed nesting' => [str_repeat('<div>', 200) . 'Text' . str_repeat('</div>', 200)];
    }

    #[DataProvider('allowedContent')]
    public function testOrdinaryContentIsAllowed(string $html): void
    {
        static::assertSame([], (new HtmlInspector())->inspect($html));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function rejectedContent(): iterable
    {
        yield 'script element' => ['<p>a</p><script>sw5csMarker()</script>', [HtmlInspector::RULE_SCRIPT_ELEMENT]];
        yield 'script element with source' => ['<SCRIPT src="https://example.org/sw5cs-marker.js"></SCRIPT>', [HtmlInspector::RULE_SCRIPT_ELEMENT]];
        yield 'event handler' => ['<img src="x.png" onerror="sw5csMarker()">', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'event handler, upper case, no quotes' => ['<VIDEO src=x.mp4 OnMouseOver=sw5csMarker()></VIDEO>', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'event handler after slash' => ['<svg/onload=sw5csMarker()>', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'javascript address in iframe' => ['<iframe src="javascript:sw5csMarker()"></iframe>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'javascript address in link, entity encoded' => ['<a href="&#106;avascript&#58;sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'javascript address with control characters' => ["<a href=\"java\tscript:sw5csMarker()\">x</a>", [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'javascript address with leading space' => ['<a href="  JavaScript:sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'vbscript address' => ['<a href="vbscript:sw5csMarker">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'html data address in object' => ['<object data="data:text/html;base64,PGI+c3c1Y3M8L2I+"></object>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'javascript address in form action' => ['<form action="javascript:sw5csMarker()"><button>x</button></form>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'srcdoc' => ['<iframe srcdoc="&lt;b&gt;sw5cs&lt;/b&gt;"></iframe>', [HtmlInspector::RULE_SRCDOC]];
        yield 'handler hidden in noscript attribute' => ['<noscript><p title="</noscript><img src=x onerror=sw5csMarker()>"></p></noscript>', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'handler after quoted greater-than inside noscript' => ["<noscript><p title=\"</noscript><img title='>' src=x onerror=sw5csMarker()>\"></p></noscript>", [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'handler after quoted greater-than in svg style' => ["<svg><style><a title='>' onclick=sw5csMarker()>x</a></style></svg>", [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'handler after quoted greater-than in svg title' => ["<svg><title><a title='>' onclick=sw5csMarker()>x</a></title></svg>", [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'script address after quoted greater-than in svg style' => ["<svg><style><a title='>' href=\"javascript:sw5csMarker()\">x</a></style></svg>", [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'srcdoc after quoted greater-than in svg title' => ["<svg><title><iframe title='>' srcdoc=\"sw5cs\"></iframe></title></svg>", [HtmlInspector::RULE_SRCDOC]];
        yield 'handler directly after quoted value' => ['<img src="x.png"onerror="sw5csMarker()">', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'slash-separated handler' => ['<img/src=x/onerror=sw5csMarker()>', [HtmlInspector::RULE_EVENT_HANDLER]];
        yield 'slash-separated script address' => ['<a/href="javascript:sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'slash-separated srcdoc' => ['<iframe/srcdoc="sw5cs"></iframe>', [HtmlInspector::RULE_SRCDOC]];
        yield 'script address with html5 entities' => ['<a href="java&Tab;script&colon;sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'script address with newline entity' => ['<a href="java&NewLine;script:sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'script address with numeric references without semicolon' => ['<a href="&#106&#97vascript&#x3A;sw5csMarker()">x</a>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'html data address in iframe' => ['<iframe src="data:text/html,sw5cs"></iframe>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'html data address with entity' => ['<iframe src="data&colon;text/html,sw5cs"></iframe>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'script address in param value' => ['<object><param name="movie" value="javascript:sw5csMarker()"></object>', [HtmlInspector::RULE_SCRIPT_URL]];
        yield 'deep nesting' => [str_repeat('<div>', 300) . 'Text', [HtmlInspector::RULE_UNPARSABLE]];
        yield 'several rules, reported once each' => ['<script></script><img onerror=a><img onload=b><a href="javascript:c">x</a>', [HtmlInspector::RULE_SCRIPT_ELEMENT, HtmlInspector::RULE_EVENT_HANDLER, HtmlInspector::RULE_SCRIPT_URL]];
    }

    /**
     * @param list<string> $rules
     */
    #[DataProvider('rejectedContent')]
    public function testScriptIsReported(string $html, array $rules): void
    {
        static::assertSame($rules, (new HtmlInspector())->inspect($html));
    }

    public function testContentBeyondTheNestingLimitIsNotSilentlyAccepted(): void
    {
        $html = str_repeat('<div>', 300) . '<object data="data:text/html,sw5cs"></object>';

        static::assertSame([HtmlInspector::RULE_SCRIPT_URL, HtmlInspector::RULE_UNPARSABLE], (new HtmlInspector())->inspect($html));
    }

    public function testLongContentIsInspectedInReasonableTime(): void
    {
        $started = microtime(true);
        $findings = (new HtmlInspector())->inspect(str_repeat('<a<a title="x', 50000) . ' onclick=sw5csMarker()>');

        static::assertContains(HtmlInspector::RULE_EVENT_HANDLER, $findings);
        static::assertLessThan(5.0, microtime(true) - $started);
    }

    public function testBrokenMarkupDoesNotHideHandlers(): void
    {
        static::assertSame([HtmlInspector::RULE_EVENT_HANDLER], (new HtmlInspector())->inspect('<p><b>unclosed <img src=x onerror=sw5csMarker()'));
    }
}
