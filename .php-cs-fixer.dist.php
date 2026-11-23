<?php

declare(strict_types=1);

/*
 * Coding standard of Sw5CommunitySecurity.
 *
 * The rule list below is taken from the Shopware 5 core
 * (.php-cs-fixer.php in https://github.com/shopware5/shopware, tag v5.7.20,
 * (c) shopware AG, AGPL-3.0-only), so the plugin follows the Shopware 5
 * coding standards exactly. Changes: file header rule (the plugin carries its
 * own GPL header instead of Shopware's), paths and cache file.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Development only: this file is not part of the release ZIP.
 */

use PhpCsFixer\Config;
use PhpCsFixerCustomFixers\Fixer\NoUselessCommentFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessDirnameCallFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessParenthesisFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessStrlenFixer;
use PhpCsFixerCustomFixers\Fixer\PhpdocNoIncorrectVarAnnotationFixer;
use PhpCsFixerCustomFixers\Fixer\PhpdocParamTypeFixer;
use PhpCsFixerCustomFixers\Fixer\PhpUnitAssertArgumentsOrderFixer;
use PhpCsFixerCustomFixers\Fixer\PhpUnitDedicatedAssertFixer;
use PhpCsFixerCustomFixers\Fixer\SingleSpaceAfterStatementFixer;
use PhpCsFixerCustomFixers\Fixer\SingleSpaceBeforeStatementFixer;
use PhpCsFixerCustomFixers\Fixers;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['build', 'vendor'])
    ->notPath('.php-cs-fixer.dist.php');

$header = <<<'EOF'
This file is part of Sw5CommunitySecurity.

(c) HQ GmbH <https://www.hqgmbh.de>

Licensed under the GNU General Public License v3.0.
See the LICENSE file distributed with this source code.
EOF;

return (new Config())
    ->registerCustomFixers(new Fixers())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,

        'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one']],
        'concat_space' => ['spacing' => 'one'],
        'doctrine_annotation_indentation' => true,
        'doctrine_annotation_spaces' => true,
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['copyright', 'category'],
        ],
        'global_namespace_import' => true,
        'header_comment' => ['header' => $header, 'comment_type' => 'comment', 'location' => 'after_declare_strict', 'separate' => 'both'],
        'modernize_types_casting' => true,
        'native_function_invocation' => ['exclude' => ['ini_get'], 'strict' => false],
        'no_alias_functions' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_superfluous_phpdoc_tags' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'operator_linebreak' => ['only_booleans' => true],
        'ordered_class_elements' => true,
        'phpdoc_line_span' => true,
        'phpdoc_order' => true,
        'phpdoc_separation' => ['groups' => [['ORM\*', 'Assert\*', 'ShopwareAssert\*'], ['deprecated', 'link', 'see', 'since'], ['author', 'copyright', 'license'], ['category', 'package', 'subpackage'], ['property', 'property-read', 'property-write']]],
        'phpdoc_summary' => false,
        'phpdoc_var_annotation_correct_order' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_construct' => true,
        'php_unit_mock' => true,
        'php_unit_mock_short_will_return' => true,
        'php_unit_test_case_static_method_calls' => true,
        'single_line_throw' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

        NoUselessCommentFixer::name() => true,
        SingleSpaceAfterStatementFixer::name() => true,
        SingleSpaceBeforeStatementFixer::name() => true,
        PhpdocParamTypeFixer::name() => true,
        NoUselessStrlenFixer::name() => true,
        NoUselessParenthesisFixer::name() => true,
        PhpUnitDedicatedAssertFixer::name() => true,
        PhpUnitAssertArgumentsOrderFixer::name() => true,
        PhpdocNoIncorrectVarAnnotationFixer::name() => true,
        NoUselessDirnameCallFixer::name() => true,
    ])
    ->setFinder($finder);
