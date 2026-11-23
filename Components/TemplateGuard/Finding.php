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
 * One disallowed Smarty construct found in a template.
 */
final class Finding
{
    public const RULE_EXTENDS = 'extends';
    public const RULE_FETCH = 'fetch';
    public const RULE_PHP = 'php';
    public const RULE_FUNCTION_NAME = 'function-name';
    public const RULE_BLOCK_NAME = 'block-name';
    public const RULE_TEMPLATE_OBJECT = 'template-object';
    public const RULE_INCLUDE_FILE = 'include-file';
    public const RULE_MATH_EQUATION = 'math-equation';
    public const RULE_TOO_DEEP = 'too-deep';
    public const RULE_STATIC_CLASS = 'static-class';
    public const RULE_TOO_LARGE = 'too-large';

    /**
     * @readonly
     */
    public string $rule;

    /**
     * @readonly
     */
    public string $tag;

    public function __construct(string $rule, string $tag)
    {
        $this->rule = $rule;
        $this->tag = $tag;
    }
}
