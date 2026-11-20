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
    private const TAG_PATTERN = '/\{(extends|fetch|php|include_php|include|math|function|block)(?=[\s}])/i';

    /**
     * Allowed values of {include file=…} in strict mode (after removing the
     * outer quotes). These cover every form used by Shopware's own database
     * templates and themes:
     * - a plain template path without "..", without a leading "/" and without
     *   quotes, braces or variables, optionally with a resource prefix;
     * - "string:{config name=…}" (mail header/footer, main features);
     * - "string:{$variable.path}" (payment and country descriptions).
     */
    private const INCLUDE_FILE_PATTERNS = [
        '~^(?:[a-z]+:)?(?!/)(?!.*\.\.)[A-Za-z0-9_./\-]+$~',
        '~^string:\{config\s+name\s*=\s*(["\']?)[A-Za-z0-9_]+\1\s*\}$~',
        '~^string:\{\$[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*\}$~',
    ];

    /** {math equation=…} in strict mode: a literal made of numbers, names, whitespace, commas and arithmetic. */
    private const MATH_EQUATION_PATTERN = '~^[A-Za-z0-9_\s.,+\-*/%()]+$~';
    private const TEMPLATE_OBJECT_PATTERN = '/\$smarty\s*\.\s*template_object/i';
    private const FUNCTION_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const BLOCK_NAME_PATTERN = '/^[A-Za-z0-9_.\-]+$/';
    private const MAX_SNIPPET_LENGTH = 80;

    /**
     * @return list<Finding>
     */
    public function inspect(string $template, bool $strict = true): array
    {
        $findings = [];

        if (preg_match_all(self::TAG_PATTERN, $template, $matches, \PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as [$tagName, $offset]) {
                $tagName = strtolower($tagName);
                if (!$strict && ($tagName === 'include' || $tagName === 'math')) {
                    continue;
                }

                $tag = $this->readTag($template, $offset - 1);
                $finding = $this->checkTag($tagName, $tag);
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
            'include' => $this->attributeMatchesAny($tag, 'file', self::INCLUDE_FILE_PATTERNS)
                ? null
                : new Finding(Finding::RULE_INCLUDE_FILE, $this->shorten($tag)),
            'math' => $this->attributeMatchesAny($tag, 'equation', [self::MATH_EQUATION_PATTERN])
                ? null
                : new Finding(Finding::RULE_MATH_EQUATION, $this->shorten($tag)),
            default => null,
        };
    }

    /**
     * True if the tag has exactly one quoted literal attribute $name whose
     * content matches one of $patterns. Unquoted values, variables and
     * expressions are never accepted.
     *
     * @param list<string> $patterns
     */
    private function attributeMatchesAny(string $tag, string $name, array $patterns): bool
    {
        $count = preg_match_all('/(?<=[\s{])' . preg_quote($name, '/') . '\s*=\s*/i', $tag, $matches, \PREG_OFFSET_CAPTURE);
        if ($count !== 1) {
            return false;
        }

        $start = $matches[0][0][1] + \strlen($matches[0][0][0]);
        $value = $this->readQuoted($tag, $start);
        if ($value === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads a quoted literal starting at $start and returns its content
     * without the outer quotes. Inside the literal, Smarty tags in braces may
     * contain quotes of their own ("string:{config name="x"}"). Returns null
     * if there is no complete quoted literal at $start.
     */
    private function readQuoted(string $tag, int $start): ?string
    {
        $quote = $tag[$start] ?? '';
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }

        $length = \strlen($tag);
        $depth = 0;
        for ($i = $start + 1; $i < $length; ++$i) {
            $char = $tag[$i];
            if ($char === '\\') {
                return null;
            }
            if ($char === '{') {
                ++$depth;
            } elseif ($char === '}') {
                if (--$depth < 0) {
                    return null;
                }
            } elseif ($char === $quote && $depth === 0) {
                return substr($tag, $start + 1, $i - $start - 1);
            }
        }

        return null;
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
