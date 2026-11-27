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
 * - {insert}        name/script attributes are evaluated at compile time
 * - {function name} name is inserted into generated PHP code (CVE-2021-26120)
 * - {block name}    unsafe block names (CVE-2022-29221)
 * - $smarty.template_object  sandbox escape (CVE-2021-26119), in every index form
 * - Class::member   static class access (CVE-2021-21408)
 *
 * Tags are delimited like the Smarty 3.1.12 lexer does it
 * (engine/Library/Smarty/sysplugins/smarty_internal_templatelexer.php:
 * single-quoted strings, double-quoted strings with embedded tags and
 * backtick expressions, nested tags). Smarty only treats "{" as a tag start
 * when it is directly followed by a non-whitespace character (auto_literal),
 * which is mirrored here, so CSS and JavaScript braces are not affected.
 *
 * The check is deliberately conservative: the tag rules and the
 * template_object rule ignore {literal} sections and comments, so a
 * disallowed construct is reported wherever it appears. The static-class
 * rule scans the template twice, once like Smarty (with {literal} sections and
 * comments) and once treating everything as code, and reports both results.
 * Attribute values are only accepted in a plain literal form; anything Smarty
 * could read as an expression is rejected.
 *
 * Free of Shopware dependencies to keep it unit-testable.
 */
final class TemplateInspector
{
    /**
     * {insert}: Smarty evaluates its name and script attributes when
     * compiling (smarty_internal_compile_insert.php); stored content has no
     * legitimate use for it.
     */
    public const RULE_INSERT = 'insert';

    /**
     * A listed tag name directly after "{". Any following character except an
     * identifier character or "(" (a PHP function call such as fetch(), not the
     * tag) keeps the match, so "{fetch|upper …}" is a tag as well.
     */
    private const TAG_PATTERN = '/\{(extends|fetch|php|include_php|include|math|function|block|insert)(?![A-Za-z0-9_(:=])/i';

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

    /**
     * {math equation=…} in strict mode: a literal made of numbers, names, whitespace, commas and arithmetic.
     */
    private const MATH_EQUATION_PATTERN = '~^[A-Za-z0-9_\s.,+\-*/%()]+$~';

    /**
     * $smarty.template_object, $smarty['template_object'], $smarty.{'template_object'} …
     */
    private const TEMPLATE_OBJECT_PATTERN = '/\$smarty[\s.\[\]{}\'"]*template_object/i';
    private const FUNCTION_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const BLOCK_NAME_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    /**
     * Unquoted names: Smarty reads anything beyond a plain identifier as an expression.
     */
    private const UNQUOTED_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Attribute start as in the Smarty lexer ("\s+[0-9]*[a-zA-Z_][a-zA-Z0-9_\-:]*\s*=\s*"),
     * applied after the leading whitespace.
     */
    private const ATTRIBUTE_NAME_PATTERN = '/\G([0-9]*[A-Za-z_][A-Za-z0-9_\-:]*)\s*=(?!=)\s*/';

    /**
     * Word operators of the Smarty lexer; after a value they continue the expression.
     */
    private const WORD_OPERATORS = [
        'is', 'in', 'as', 'to', 'step', 'instanceof', 'eq', 'ne', 'neq', 'ge', 'gte', 'le', 'lte',
        'gt', 'lt', 'mod', 'not', 'and', 'or', 'xor', 'odd', 'even', 'div', 'by',
    ];

    private const LITERAL_START_PATTERN = '/\G\{\s*literal\s*\}/i';
    private const LITERAL_END_PATTERN = '/\{\s*\/literal\s*\}/i';
    private const WHITESPACE = " \t\n\r\v\f";

    /**
     * Placeholder for characters inside strings and nested tags in the masks built by lexTag().
     */
    private const MASK = 'x';

    private const STATE_TAG = 1;
    private const STATE_BACKTICK = 2;
    private const STATE_STRING = 3;
    private const MAX_SNIPPET_LENGTH = 80;

    /**
     * Characters the tag checks may read per template character (nested tags
     * are read again for each listed tag inside). Templates that need more
     * are reported as too large instead of being checked for minutes.
     */
    private const LEX_BUDGET_FACTOR = 8;
    private const LEX_BUDGET_MINIMUM = 65536;

    /**
     * @return list<Finding>
     */
    public function inspect(string $template, bool $strict = true): array
    {
        $findings = [];
        $budget = self::LEX_BUDGET_FACTOR * \strlen($template) + self::LEX_BUDGET_MINIMUM;

        if (preg_match_all(self::TAG_PATTERN, $template, $matches, \PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as [$tagName, $offset]) {
                $tagName = strtolower($tagName);
                if (!$strict && ($tagName === 'include' || $tagName === 'math')) {
                    continue;
                }

                $finding = $this->checkTag($tagName, $this->lexTag($template, $offset - 1, $budget));
                if ($finding !== null) {
                    $findings[] = $finding;
                }
                if ($budget <= 0) {
                    $findings[] = new Finding(Finding::RULE_TOO_LARGE, '');
                    break;
                }
            }
        }

        if (preg_match_all(self::TEMPLATE_OBJECT_PATTERN, $template, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $findings[] = new Finding(Finding::RULE_TEMPLATE_OBJECT, $this->shorten($match));
            }
        }

        foreach ($this->findStaticClassAccess($template) as $tag) {
            $findings[] = new Finding(Finding::RULE_STATIC_CLASS, $this->shorten($tag));
        }

        return $findings;
    }

    /**
     * Smarty tags containing "::" outside single-quoted strings (static class
     * access such as Class::method(), Class::$property or Class::CONSTANT,
     * CVE-2021-21408). Shopware leaves the sandbox's static_classes empty,
     * which allows every class in Smarty 3.1.12. Double-quoted strings are
     * searched as well, they may contain tags.
     *
     * @return list<string>
     */
    private function findStaticClassAccess(string $template): array
    {
        $tags = $this->scanStaticClassAccess($template, true) + $this->scanStaticClassAccess($template, false);
        ksort($tags);

        return array_values($tags);
    }

    /**
     * With $likeSmarty, comments and {literal} sections are skipped like the
     * Smarty lexer does it (TEXT and LITERAL states). A {literal} section ends
     * at the first closing tag, which is never later than in Smarty. Without
     * $likeSmarty, every "{" outside a tag starts a tag.
     *
     * @return array<int, string> offset => tag
     */
    private function scanStaticClassAccess(string $template, bool $likeSmarty): array
    {
        $tags = [];
        $length = \strlen($template);
        $position = 0;
        $literalEnds = null;
        $literalIndex = 0;
        // Tags are read one after the other here, so the work is linear.
        $budget = \PHP_INT_MAX;
        $commentsClosed = true;

        while ($position < $length && ($start = strpos($template, '{', $position)) !== false) {
            if (!$this->startsTag($template, $start)) {
                $position = $start + 1;
                continue;
            }

            if ($template[$start + 1] === '*') {
                $commentEnd = $likeSmarty && $commentsClosed ? strpos($template, '*}', $start + 2) : false;
                if ($commentEnd === false) {
                    // No closing "*}" any more: Smarty fails to compile, keep scanning.
                    $commentsClosed = false;
                    $position = $start + 1;
                } else {
                    $position = $commentEnd + 2;
                }
                continue;
            }

            if ($likeSmarty && preg_match(self::LITERAL_START_PATTERN, $template, $match, 0, $start) === 1) {
                if ($literalEnds === null) {
                    $literalEnds = preg_match_all(self::LITERAL_END_PATTERN, $template, $ends, \PREG_OFFSET_CAPTURE) > 0 ? $ends[0] : [];
                }
                $contentStart = $start + \strlen($match[0]);
                // Offsets only grow, so the search continues where the last one stopped.
                while (isset($literalEnds[$literalIndex]) && $literalEnds[$literalIndex][1] < $contentStart) {
                    ++$literalIndex;
                }
                // Without a closing tag Smarty fails to compile; keep scanning.
                $position = isset($literalEnds[$literalIndex])
                    ? $literalEnds[$literalIndex][1] + \strlen($literalEnds[$literalIndex][0])
                    : $contentStart;
                continue;
            }

            [$tag, $code] = $this->lexTag($template, $start, $budget);
            if (strpos($code, '::') !== false) {
                $tags[$start] = $tag;
            }

            $position = $start + max(1, \strlen($tag));
        }

        return $tags;
    }

    /**
     * @param array{string, string, string, bool} $lexed see lexTag()
     */
    private function checkTag(string $tagName, array $lexed): ?Finding
    {
        [$tag, , $top, $closed] = $lexed;

        switch ($tagName) {
            case 'extends':
                return new Finding(Finding::RULE_EXTENDS, $this->shorten($tag));
            case 'fetch':
                return new Finding(Finding::RULE_FETCH, $this->shorten($tag));
            case 'php':
            case 'include_php':
                return new Finding(Finding::RULE_PHP, $this->shorten($tag));
            case 'insert':
                return new Finding(self::RULE_INSERT, $this->shorten($tag));
            case 'function':
                return $closed && $this->hasValidName($tagName, $tag, $top, self::FUNCTION_NAME_PATTERN)
                    ? null
                    : new Finding(Finding::RULE_FUNCTION_NAME, $this->shorten($tag));
            case 'block':
                return $closed && $this->hasValidName($tagName, $tag, $top, self::BLOCK_NAME_PATTERN)
                    ? null
                    : new Finding(Finding::RULE_BLOCK_NAME, $this->shorten($tag));
            case 'include':
                return $closed && $this->hasValidAttribute($tagName, $tag, $top, 'file', self::INCLUDE_FILE_PATTERNS, true)
                    ? null
                    : new Finding(Finding::RULE_INCLUDE_FILE, $this->shorten($tag));
            case 'math':
                return $closed && $this->hasValidAttribute($tagName, $tag, $top, 'equation', [self::MATH_EQUATION_PATTERN], false)
                    ? null
                    : new Finding(Finding::RULE_MATH_EQUATION, $this->shorten($tag));
            default:
                return null;
        }
    }

    /**
     * True if the tag has exactly one attribute $name (or, if $shorthand is
     * allowed, a first value without name instead) and its value is a quoted
     * literal matching one of $patterns that is not continued as an expression.
     *
     * @param list<string> $patterns
     */
    private function hasValidAttribute(string $tagName, string $tag, string $top, string $name, array $patterns, bool $shorthand): bool
    {
        $attributes = $this->attributes($tagName, $tag, $top);
        if ($attributes === null) {
            return false;
        }

        $found = $this->singleAttribute($attributes, $name, $shorthand);
        if ($found === null || !$this->endsValue($found[1])) {
            return false;
        }

        $value = $this->quotedLiteral($found[0]['raw'], $found[0]['top']);
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
     * Accepts "{tag name=foo ...}", "{tag name='foo'}", "{tag name="foo"}"
     * and the shorthand "{tag foo}" / "{tag 'foo'}". Smarty uses the whole
     * expression of the last name attribute, so exactly one name is allowed
     * and it must not be continued as an expression.
     */
    private function hasValidName(string $tagName, string $tag, string $top, string $pattern): bool
    {
        $attributes = $this->attributes($tagName, $tag, $top);
        if ($attributes === null) {
            return false;
        }

        $found = $this->singleAttribute($attributes, 'name', true);
        if ($found === null || !$this->endsValue($found[1])) {
            return false;
        }

        $raw = $found[0]['raw'];
        $name = $this->quotedLiteral($raw, $found[0]['top']);
        if ($name === null && $raw === $found[0]['top'] && preg_match(self::UNQUOTED_NAME_PATTERN, $raw) === 1) {
            $name = $raw;
        }

        return $name !== null && preg_match($pattern, $name) === 1;
    }

    /**
     * The only attribute $name (case-insensitive) or, with $shorthand, a
     * first value without name, together with the attribute after it. Null
     * if there is none or more than one.
     *
     * @param list<array{name: ?string, raw: string, top: string}> $attributes
     *
     * @return array{array{name: ?string, raw: string, top: string}, array{name: ?string, raw: string, top: string}|null}|null
     */
    private function singleAttribute(array $attributes, string $name, bool $shorthand): ?array
    {
        $found = null;
        $count = 0;
        foreach ($attributes as $index => $attribute) {
            if ($attribute['name'] === $name || ($index === 0 && $attribute['name'] === null)) {
                ++$count;
                $found = [$attribute, $attributes[$index + 1] ?? null];
            }
        }

        if ($count !== 1 || $found === null) {
            return null;
        }

        return $shorthand || $found[0]['name'] !== null ? $found : null;
    }

    /**
     * True if a value is not continued by an operator: the next attribute,
     * if any, is a named attribute or a plain flag (nocache, append …).
     *
     * @param array{name: ?string, raw: string, top: string}|null $next
     */
    private function endsValue(?array $next): bool
    {
        if ($next === null || $next['name'] !== null) {
            return true;
        }

        return preg_match(self::UNQUOTED_NAME_PATTERN, $next['raw']) === 1
            && !\in_array(strtolower($next['raw']), self::WORD_OPERATORS, true);
    }

    /**
     * Splits the attributes of a closed tag at top-level whitespace. Each
     * entry has the lower-case attribute name (null for a value without
     * name), the raw value and the value from the top-level mask. Returns
     * null if the tag name is not followed by whitespace or the end of the tag
     * (e.g. a modifier "{include|upper …}").
     *
     * @return list<array{name: ?string, raw: string, top: string}>|null
     */
    private function attributes(string $tagName, string $tag, string $top): ?array
    {
        $offset = 1 + \strlen($tagName);
        $raw = (string) substr($tag, $offset, -1);
        $body = (string) substr($top, $offset, -1);
        $length = \strlen($body);
        $attributes = [];
        $position = 0;

        while ($position < $length) {
            $space = strspn($body, self::WHITESPACE, $position);
            if ($space === 0) {
                return null;
            }
            $position += $space;
            if ($position >= $length) {
                break;
            }

            $name = null;
            if (preg_match(self::ATTRIBUTE_NAME_PATTERN, $body, $match, 0, $position) === 1) {
                $name = strtolower($match[1]);
                $position += \strlen($match[0]);
            }

            $valueLength = strcspn($body, self::WHITESPACE, $position);
            $attributes[] = [
                'name' => $name,
                'raw' => (string) substr($raw, $position, $valueLength),
                'top' => (string) substr($body, $position, $valueLength),
            ];
            $position += $valueLength;
        }

        return $attributes;
    }

    /**
     * Content of a value that consists of exactly one quoted string without
     * escapes, or null.
     */
    private function quotedLiteral(string $raw, string $top): ?string
    {
        $quote = $raw[0] ?? '';
        $length = \strlen($top);
        if (($quote !== '"' && $quote !== "'") || $length < 2 || $top[$length - 1] !== $quote
            || strspn($top, self::MASK, 1, $length - 2) !== $length - 2) {
            return null;
        }

        $content = (string) substr($raw, 1, -1);

        return strpos($content, '\\') === false ? $content : null;
    }

    /**
     * Reads the tag that starts with "{" at $start like the Smarty 3.1.12
     * lexer (states SMARTY and DOUBLEQUOTEDSTRING): single-quoted strings,
     * double-quoted strings with embedded tags ("{…}") and backtick
     * expressions ("`$…`"), nested tags; "}" and "`" end the current tag.
     * Iterative, so deeply nested input cannot exhaust the stack. Each read
     * character is taken from $budget; when it is used up, the tag counts as
     * not closed.
     *
     * Returns
     *  0: the tag text (to the end of the template if it is not closed),
     *  1: the same with the content of single-quoted strings masked (code),
     *  2: the same with everything inside strings and nested tags masked (top level),
     *  3: whether the tag is closed.
     *
     * @return array{string, string, string, bool}
     */
    private function lexTag(string $template, int $start, int &$budget): array
    {
        $length = \strlen($template);
        if ($budget < $length - $start) {
            $length = $start + max(0, $budget);
        }
        $stack = [self::STATE_TAG];
        $code = '{';
        $top = '{';
        $i = $start + 1;

        while ($i < $length) {
            $char = $template[$i];
            $depth = \count($stack);

            if (end($stack) === self::STATE_STRING) {
                if ($char === '\\') {
                    $escaped = (string) substr($template, $i, 2);
                    $code .= $escaped;
                    $top .= str_repeat(self::MASK, \strlen($escaped));
                    $i += 2;
                    continue;
                }
                if ($char === '"') {
                    array_pop($stack);
                } elseif ($this->startsTag($template, $i)) {
                    $stack[] = self::STATE_TAG;
                } elseif ($char === '`' && ($template[$i + 1] ?? '') === '$') {
                    $stack[] = self::STATE_BACKTICK;
                }
                $code .= $char;
                $top .= $char === '"' && $depth === 2 ? $char : self::MASK;
                ++$i;
                continue;
            }

            // Single-quoted string as in the lexer: '[^'\\]*(?:\\.[^'\\]*)*'
            if ($char === "'" && preg_match("/\\G'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", $template, $match, 0, $i) === 1) {
                $masked = "'" . str_repeat(self::MASK, \strlen($match[0]) - 2) . "'";
                $code .= $masked;
                $top .= $depth === 1 ? $masked : str_repeat(self::MASK, \strlen($match[0]));
                $i += \strlen($match[0]);
                continue;
            }

            $code .= $char;
            $top .= $depth === 1 ? $char : self::MASK;

            if ($char === '"') {
                $stack[] = self::STATE_STRING;
            } elseif ($this->startsTag($template, $i)) {
                $stack[] = self::STATE_TAG;
            } elseif ($char === '}' || $char === '`') {
                array_pop($stack);
                if ($stack === []) {
                    $budget -= $i + 1 - $start;

                    return [(string) substr($template, $start, $i - $start + 1), $code, $top, true];
                }
            }
            ++$i;
        }

        $budget -= $i - $start;

        return [(string) substr($template, $start, $i - $start), $code, $top, false];
    }

    /**
     * Smarty only starts a tag at "{" directly followed by a character that
     * is neither whitespace nor NUL (auto_literal: the lexer's "\s" rules plus
     * trim() in yy_r1_16/yy_r2_10).
     */
    private function startsTag(string $template, int $offset): bool
    {
        if ($template[$offset] !== '{') {
            return false;
        }
        $next = $template[$offset + 1] ?? '';

        return $next !== '' && $next !== "\0" && !ctype_space($next);
    }

    private function shorten(string $tag): string
    {
        $tag = preg_replace('/\s+/', ' ', $tag) ?? $tag;

        return mb_strlen($tag) > self::MAX_SNIPPET_LENGTH
            ? mb_substr($tag, 0, self::MAX_SNIPPET_LENGTH - 1) . '…'
            : $tag;
    }
}
