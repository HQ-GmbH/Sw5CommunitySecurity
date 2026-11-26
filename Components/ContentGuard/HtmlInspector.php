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
use DOMElement;

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
 *  - javascript:, vbscript: and HTML data: addresses in URL attributes,
 *  - srcdoc (HTML document inside an iframe).
 *
 * The markup is parsed like a browser would (DOMDocument, entities decoded,
 * control characters and whitespace in addresses ignored). In addition, the
 * raw text is searched for tags with event handlers or script addresses, so
 * markup that the browser reads differently than the parser (e.g. inside
 * <noscript> or attribute values) is reported as well.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class HtmlInspector
{
    public const RULE_SCRIPT_ELEMENT = 'script-element';

    public const RULE_EVENT_HANDLER = 'event-handler';

    public const RULE_SCRIPT_URL = 'script-url';

    public const RULE_SRCDOC = 'srcdoc';

    /**
     * Attributes that browsers load or navigate to.
     */
    private const URL_ATTRIBUTES = [
        'href', 'src', 'action', 'formaction', 'data', 'poster', 'background', 'codebase',
        'cite', 'longdesc', 'lowsrc', 'dynsrc', 'xlink:href', 'xml:base', 'manifest', 'value',
    ];

    private const SCRIPT_URL_PATTERN = '/^(?:javascript|vbscript|livescript|data:text\/html|data:application\/xhtml|data:text\/xml)/i';

    private const RAW_EVENT_HANDLER_PATTERN = '/<[a-z][^>]*?[\s\/]on[a-z]+\s*=/i';

    private const RAW_SCRIPT_PATTERN = '/<\s*script[\s>\/]/i';

    private const RAW_SCRIPT_URL_PATTERN = '/<[a-z][^>]*?\b(?:href|src|action|formaction|data|xlink:href)\s*=\s*["\']?\s*(?:javascript|vbscript)\s*:/i';

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
        if (preg_match(self::RAW_EVENT_HANDLER_PATTERN, $html) === 1) {
            $found[self::RULE_EVENT_HANDLER] = true;
        }
        if (preg_match(self::RAW_SCRIPT_URL_PATTERN, $html) === 1) {
            $found[self::RULE_SCRIPT_URL] = true;
        }

        foreach ($this->elements($html) as $element) {
            if (strtolower($element->tagName) === 'script') {
                $found[self::RULE_SCRIPT_ELEMENT] = true;
            }
            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->nodeName);
                if (strncmp($name, 'on', 2) === 0) {
                    $found[self::RULE_EVENT_HANDLER] = true;
                } elseif ($name === 'srcdoc') {
                    $found[self::RULE_SRCDOC] = true;
                } elseif (\in_array($name, self::URL_ATTRIBUTES, true) && self::isScriptUrl((string) $attribute->nodeValue)) {
                    $found[self::RULE_SCRIPT_URL] = true;
                }
            }
        }

        $rules = [];
        foreach ([self::RULE_SCRIPT_ELEMENT, self::RULE_EVENT_HANDLER, self::RULE_SCRIPT_URL, self::RULE_SRCDOC] as $rule) {
            if (isset($found[$rule])) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    private static function isScriptUrl(string $value): bool
    {
        // Browsers ignore whitespace and control characters in the scheme.
        $normalized = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $value);

        return preg_match(self::SCRIPT_URL_PATTERN, $normalized) === 1;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>', \LIBXML_NONET | \LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $elements = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }

        return $elements;
    }
}
