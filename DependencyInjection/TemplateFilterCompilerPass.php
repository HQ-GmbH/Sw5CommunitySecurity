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

namespace Sw5CommunitySecurity\DependencyInjection;

use Sw5CommunitySecurity\Components\JavaScriptEscape\CompiledTemplateFilter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the compiled-template filter on Shopware's "template" service.
 *
 * A method call on the service definition takes effect whenever the template
 * engine is created, also when another service receives it as a constructor
 * argument; an "Enlight_Bootstrap_AfterInitResource_template" listener would
 * miss that case. Shopware only builds compiler passes of active plugins into
 * the container, so the filter is gone once the plugin is deactivated.
 */
final class TemplateFilterCompilerPass implements CompilerPassInterface
{
    public const TEMPLATE_SERVICE = 'template';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::TEMPLATE_SERVICE)) {
            return;
        }

        $container->getDefinition(self::TEMPLATE_SERVICE)->addMethodCall(
            'registerFilter',
            ['post', [new Reference(CompiledTemplateFilter::class), 'filter']],
        );
    }
}
