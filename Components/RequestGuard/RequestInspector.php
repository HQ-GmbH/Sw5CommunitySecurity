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

namespace Sw5CommunitySecurity\Components\RequestGuard;

use Sw5CommunitySecurity\Components\TemplateGuard\TemplateInspector;

/**
 * Removes values with Smarty constructs of known Smarty vulnerabilities from
 * request data (query, post, cookies, route parameters) of storefront
 * requests.
 *
 * Shopware compiles some strings as Smarty templates, e.g. form error
 * messages in the storefront (Controllers/Frontend/Register.php:426,
 * Account.php:606/672: fetch('string:' . $error->getMessage())). If a plugin
 * puts the submitted value into such a message, customer input is rendered
 * as Smarty. Shopware's input filter
 * (Plugins/Default/Frontend/InputFilter) strips HTML tags but keeps Smarty
 * braces. Like that filter, values are not rejected but cleared (null), so
 * the request continues; the same rules as the template guard apply, so plain
 * text, JSON and harmless braces pass.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class RequestInspector
{
    public const MAX_DEPTH = 8;

    public const RULE_TOO_DEEP = 'too-deep';

    /**
     * @readonly
     */
    private TemplateInspector $inspector;

    public function __construct(TemplateInspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array{0: array<array-key, mixed>, 1: array<string, list<string>>} cleaned values and removed paths with rules
     */
    public function clean(array $values, string $prefix = ''): array
    {
        $removed = [];
        $cleaned = $this->cleanLevel($values, $prefix, 0, $removed);

        return [$cleaned, $removed];
    }

    /**
     * @param array<array-key, mixed>     $values
     * @param array<string, list<string>> $removed
     *
     * @return array<array-key, mixed>
     */
    private function cleanLevel(array $values, string $prefix, int $depth, array &$removed): array
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            // Keys can carry templates as well (e.g. form fields named after the input).
            $keyRules = \is_string($key) ? $this->rules($key) : [];
            if ($keyRules !== []) {
                unset($values[$key]);
                // The key itself is the payload: it is not used as path (logs never contain content).
                $removed[$prefix === '' ? '[key]' : $prefix . '.[key]'] = $keyRules;

                continue;
            }

            if (\is_array($value)) {
                if ($depth + 1 >= self::MAX_DEPTH) {
                    $values[$key] = null;
                    $removed[$path] = [self::RULE_TOO_DEEP];

                    continue;
                }
                $values[$key] = $this->cleanLevel($value, $path, $depth + 1, $removed);

                continue;
            }

            if (!\is_string($value)) {
                continue;
            }

            $rules = $this->rules($value);
            if ($rules !== []) {
                $values[$key] = null;
                $removed[$path] = $rules;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function rules(string $value): array
    {
        if (strpos($value, '{') === false) {
            return [];
        }

        $rules = [];
        foreach ($this->inspector->inspect($value, true) as $finding) {
            $rules[] = $finding->rule;
        }

        return array_values(array_unique($rules));
    }
}
