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

namespace Sw5CommunitySecurity\Components\TemplateGuard;

/**
 * Finds Smarty constructs that have no legitimate use in templates stored in
 * the database (mail, newsletter, document, export templates) but are known
 * attack vectors in the Smarty 3.1.12 bundled with Shopware 5:
 *
 * - {extends}       file attribute is passed to eval() (CVE-2024-35226)
 * - {fetch}         reads local files / remote URLs
 * - {php}, {include_php}
 * - {function name} name is inserted into generated PHP code (CVE-2021-26120)
 * - {block name}    unsafe block names (CVE-2022-29221)
 * - $smarty.template_object  sandbox escape (CVE-2021-26119)
 *
 * The check is deliberately conservative: it does not try to understand
 * {literal} sections or comments, so a disallowed construct is reported
 * wherever it appears. Smarty only treats "{" as a tag start when it is
 * directly followed by a non-whitespace character (auto_literal), which is
 * mirrored here, so CSS and JavaScript braces are not affected.
 *
 * Free of Shopware dependencies to keep it unit-testable.
 */
final class TemplateInspector
{
    private const TAG_PATTERN = '/\{(extends|fetch|php|include_php|function|block)(?=[\s}])/i';
    private const TEMPLATE_OBJECT_PATTERN = '/\$smarty\s*\.\s*template_object/i';
    private const FUNCTION_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const BLOCK_NAME_PATTERN = '/^[A-Za-z0-9_.\-]+$/';
    private const MAX_SNIPPET_LENGTH = 80;

    /**
     * @return list<Finding>
     */
    public function inspect(string $template): array
    {
        $findings = [];

        if (preg_match_all(self::TAG_PATTERN, $template, $matches, \PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as [$tagName, $offset]) {
                $tag = $this->readTag($template, $offset - 1);
                $finding = $this->checkTag(strtolower($tagName), $tag);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        if (preg_match_all(self::TEMPLATE_OBJECT_PATTERN, $template, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $findings[] = new Finding(Finding::RULE_TEMPLATE_OBJECT, $this->shorten($match));
            }
        }

        return $findings;
    }

    private function checkTag(string $tagName, string $tag): ?Finding
    {
        return match ($tagName) {
            'extends' => new Finding(Finding::RULE_EXTENDS, $this->shorten($tag)),
            'fetch' => new Finding(Finding::RULE_FETCH, $this->shorten($tag)),
            'php', 'include_php' => new Finding(Finding::RULE_PHP, $this->shorten($tag)),
            'function' => $this->hasValidName($tag, $tagName, self::FUNCTION_NAME_PATTERN)
                ? null
                : new Finding(Finding::RULE_FUNCTION_NAME, $this->shorten($tag)),
            'block' => $this->hasValidName($tag, $tagName, self::BLOCK_NAME_PATTERN)
                ? null
                : new Finding(Finding::RULE_BLOCK_NAME, $this->shorten($tag)),
            default => null,
        };
    }

    /**
     * Returns the tag starting at $start up to its closing brace, honouring
     * nested braces and quoted strings. An unclosed tag runs to the end.
     */
    private function readTag(string $template, int $start): string
    {
        $length = \strlen($template);
        $depth = 0;
        $quote = null;

        for ($i = $start; $i < $length; ++$i) {
            $char = $template[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    ++$i;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                ++$depth;
            } elseif ($char === '}') {
                --$depth;
                if ($depth === 0) {
                    return substr($template, $start, $i - $start + 1);
                }
            }
        }

        return substr($template, $start);
    }

    /**
     * Accepts "{tag name=foo ...}", "{tag name='foo'}", "{tag name="foo"}"
     * and the shorthand "{tag foo}" / "{tag 'foo'}".
     */
    private function hasValidName(string $tag, string $tagName, string $pattern): bool
    {
        $body = trim(substr($tag, \strlen($tagName) + 1));
        $body = str_ends_with($body, '}') ? substr($body, 0, -1) : $body;

        if (preg_match('/(?:^|\s)name\s*=\s*(.+)$/s', $body, $match) === 1) {
            $value = $match[1];
        } else {
            $value = $body;
        }

        $name = $this->firstToken(trim($value));

        return $name !== null && preg_match($pattern, $name) === 1;
    }

    /**
     * Returns the first value token: a fully quoted string without the quotes,
     * or an unquoted token up to the next whitespace. Returns null if the value
     * is not a plain literal (unterminated or nested quotes, expressions).
     */
    private function firstToken(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $first = $value[0];
        if ($first === '"' || $first === "'") {
            $end = strpos($value, $first, 1);
            if ($end === false) {
                return null;
            }

            return substr($value, 1, $end - 1);
        }

        $token = preg_split('/\s/', $value, 2)[0] ?? '';

        return $token === '' ? null : $token;
    }

    private function shorten(string $tag): string
    {
        $tag = preg_replace('/\s+/', ' ', $tag) ?? $tag;

        return mb_strlen($tag) > self::MAX_SNIPPET_LENGTH
            ? mb_substr($tag, 0, self::MAX_SNIPPET_LENGTH - 1) . '…'
            : $tag;
    }
}
