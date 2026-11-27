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

namespace Sw5CommunitySecurity\Components\ContentGuard;

use DOMDocument;

/**
 * Finds script in HTML that the backend edits with TinyMCE (shop pages,
 * product and category texts, blog, manufacturers, forms, ...).
 *
 * Shopware configures TinyMCE without clean-up and explicitly allows iframes,
 * embeds and event handlers on media elements
 * (themes/Backend/ExtJs/backend/base/component/Shopware.form.field.TinyMCE.js),
 * so such content is executed in the backend as soon as it is opened in the
 * editor. Formatting, links, images, tables, iframes and embeds with normal
 * addresses stay allowed; only script is reported:
 *  - <script> elements,
 *  - event handler attributes (on...),
 *  - javascript:, vbscript: and HTML data: addresses in URL attributes
 *    (value only on <param>),
 *  - srcdoc (HTML document inside an iframe),
 *  - markup the parser could not read completely (e.g. nesting deeper than
 *    libxml's limit of 256 levels); it cannot be checked and is rejected.
 *
 * The markup is parsed like a browser would (DOMDocument, entities decoded,
 * control characters and whitespace in addresses ignored). In addition, the
 * raw text is searched for tags with event handlers, srcdoc or script
 * addresses, so markup that the browser reads differently than libxml (e.g.
 * inside <noscript>, <style> or <title> within <svg>, attribute values, or
 * attributes separated by "/" that libxml 2.9 drops) is reported as well. The
 * raw search follows every possible reading of a tag at the same time: up to
 * the first ">" and up to the first ">" outside quoted attribute values.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class HtmlInspector
{
    public const RULE_SCRIPT_ELEMENT = 'script-element';

    public const RULE_EVENT_HANDLER = 'event-handler';

    public const RULE_SCRIPT_URL = 'script-url';

    public const RULE_SRCDOC = 'srcdoc';

    public const RULE_UNPARSABLE = 'unparsable';

    private const ORDERED_RULES = [
        self::RULE_SCRIPT_ELEMENT, self::RULE_EVENT_HANDLER, self::RULE_SCRIPT_URL, self::RULE_SRCDOC, self::RULE_UNPARSABLE,
    ];

    /**
     * Attributes that browsers load or navigate to. "value" only counts on
     * <param> (plugin parameters), elsewhere it is plain data.
     */
    private const URL_ATTRIBUTES = [
        'href', 'src', 'action', 'formaction', 'data', 'poster', 'background', 'codebase',
        'cite', 'longdesc', 'lowsrc', 'dynsrc', 'xlink:href', 'xml:base', 'manifest', 'value',
    ];

    private const VALUE_URL_ELEMENT = 'param';

    /**
     * Matched after decoding entities and removing control characters and whitespace.
     */
    private const SCRIPT_URL_PATTERN = '/^(?:javascript:|vbscript:|livescript:|data:text\/html|data:application\/xhtml|data:text\/xml)/i';

    private const RAW_SCRIPT_PATTERN = '/<\s*script[\s>\/]/i';

    /**
     * An attribute in the raw text, anchored at a possible attribute start.
     */
    private const RAW_ATTRIBUTE_PATTERN = '/\G(on[a-z]+|srcdoc|href|src|action|formaction|data|poster|background|codebase|cite|longdesc|lowsrc|dynsrc|xlink:href|xml:base|manifest|value)\s*=\s*+("[^"]*+"|\'[^\']*+\'|[^\s>]*+)/i';

    private const RAW_TAG_NAME_PATTERN = '/\G<([a-z][^\s\/>]*)/i';

    /**
     * libxml error code for exceeded resource limits ("Excessive depth in document").
     */
    private const LIBXML_ERROR_RESOURCE_LIMIT = 114;

    private const WHITESPACE = " \t\n\r\f";

    /**
     * @return list<self::RULE_*> rules found, each once, in the order above
     */
    public function inspect(string $html): array
    {
        if (strpos($html, '<') === false) {
            return [];
        }

        $found = [];
        if (preg_match(self::RAW_SCRIPT_PATTERN, $html) === 1) {
            $found[self::RULE_SCRIPT_ELEMENT] = true;
        }
        $this->inspectRaw($html, $found);
        $this->inspectDocument($html, $found);

        $rules = [];
        foreach (self::ORDERED_RULES as $rule) {
            if (isset($found[$rule])) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Browsers decode character references (also numeric ones without ";")
     * and ignore whitespace and control characters in the scheme. Decoding a
     * value that the parser already decoded only makes the check stricter.
     */
    private static function isScriptUrl(string $value): bool
    {
        $decoded = preg_replace_callback(
            '/&#(?:[xX]([0-9a-fA-F]{1,8})|([0-9]{1,10}));?/',
            static function (array $match): string {
                $codePoint = isset($match[2]) ? (int) $match[2] : (int) hexdec($match[1]);
                $char = $codePoint > 0 && $codePoint <= 0x10FFFF ? mb_chr($codePoint, 'UTF-8') : false;

                return \is_string($char) ? $char : "\u{FFFD}";
            },
            $value,
        ) ?? $value;
        $decoded = html_entity_decode($decoded, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded) ?? $decoded;

        return preg_match(self::SCRIPT_URL_PATTERN, $normalized) === 1;
    }

    /**
     * Searches the raw text for event handlers, srcdoc and script addresses
     * inside tags. Several readings of the markup are followed at once, each
     * as a flag; readings in the same state are identical from then on, so
     * one pass over the text is enough:
     *  - $toAngle:  in a tag up to the next ">" (quotes ignored),
     *  - $inTag:    in a tag outside attribute values (quote-aware),
     *  - $afterEq:  after "=" before an attribute value,
     *  - $inDouble/$inSingle: in a quoted attribute value.
     * Every "<" followed by a letter starts a tag in all readings, also inside
     * attribute values or raw text, where another parser may see a tag.
     *
     * @param array<string, true> $found
     */
    private function inspectRaw(string $html, array &$found): void
    {
        $length = \strlen($html);
        $toAngle = $inTag = $afterEq = $inDouble = $inSingle = false;
        $tagName = '';
        $i = 0;

        while ($i < $length) {
            if (!$toAngle && !$inTag && !$afterEq && !$inDouble && !$inSingle) {
                $next = strpos($html, '<', $i);
                if ($next === false) {
                    return;
                }
                $i = $next;
            }

            $char = $html[$i];
            $space = strpos(self::WHITESPACE, $char) !== false;
            $attributeFollows = ($space || $char === '/') && ($toAngle || $inTag)
                || ($char === '"' && $inDouble) || ($char === "'" && $inSingle);

            $nextInTag = ($inTag && $char !== '>' && $char !== '=')
                || ($afterEq && !$space && $char !== '"' && $char !== "'" && $char !== '>')
                || ($inDouble && $char === '"') || ($inSingle && $char === "'");
            $nextAfterEq = ($inTag && $char === '=') || ($afterEq && $space);
            $nextInDouble = ($afterEq && $char === '"') || ($inDouble && $char !== '"');
            $nextInSingle = ($afterEq && $char === "'") || ($inSingle && $char !== "'");
            $toAngle = $toAngle && $char !== '>';

            if ($char === '<' && preg_match(self::RAW_TAG_NAME_PATTERN, $html, $match, 0, $i) === 1) {
                $tagName = strtolower($match[1]);
                $toAngle = true;
                $nextInTag = true;
                // The tag name cannot contain an attribute.
                $i += \strlen($match[0]) - 1;
            }

            $inTag = $nextInTag;
            $afterEq = $nextAfterEq;
            $inDouble = $nextInDouble;
            $inSingle = $nextInSingle;
            ++$i;

            if ($attributeFollows && preg_match(self::RAW_ATTRIBUTE_PATTERN, $html, $match, 0, $i) === 1) {
                $this->checkAttribute(strtolower($match[1]), $this->unquote($match[2]), $tagName, $found);
            }
        }
    }

    /**
     * @param array<string, true> $found
     */
    private function inspectDocument(string $html, array &$found): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>', \LIBXML_NONET | \LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // libxml stops at a fatal error (e.g. nesting deeper than 256 levels) but
        // still reports success; the rest of the content was never checked.
        foreach ($errors as $error) {
            if ($error->level === \LIBXML_ERR_FATAL || $error->code === self::LIBXML_ERROR_RESOURCE_LIMIT) {
                $found[self::RULE_UNPARSABLE] = true;
            }
        }
        if (!$loaded) {
            $found[self::RULE_UNPARSABLE] = true;

            return;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            $tagName = strtolower($element->tagName);
            if ($tagName === 'script') {
                $found[self::RULE_SCRIPT_ELEMENT] = true;
            }
            foreach ($element->attributes as $attribute) {
                $this->checkAttribute(strtolower($attribute->nodeName), (string) $attribute->nodeValue, $tagName, $found);
            }
        }
    }

    /**
     * @param array<string, true> $found
     */
    private function checkAttribute(string $name, string $value, string $tagName, array &$found): void
    {
        if (strncmp($name, 'on', 2) === 0) {
            $found[self::RULE_EVENT_HANDLER] = true;
        } elseif ($name === 'srcdoc') {
            $found[self::RULE_SRCDOC] = true;
        } elseif (\in_array($name, self::URL_ATTRIBUTES, true)
            && ($name !== 'value' || $tagName === self::VALUE_URL_ELEMENT)
            && self::isScriptUrl($value)) {
            $found[self::RULE_SCRIPT_URL] = true;
        }
    }

    private function unquote(string $value): string
    {
        $first = $value[0] ?? '';

        return ($first === '"' || $first === "'") ? (string) substr($value, 1, -1) : $value;
    }
}
