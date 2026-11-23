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

namespace Sw5CommunitySecurity;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;

/**
 * Plugin bootstrap.
 *
 * Services and event subscribers live in Resources/services.xml, the backend
 * configuration form in Resources/config.xml. Shopware synchronises both
 * automatically, so the lifecycle methods only take care of cache invalidation.
 */
class Sw5CommunitySecurity extends Plugin
{
    /**
     * Security headers end up in responses stored by the Shopware HTTP cache,
     * so the HTTP cache has to be cleared together with the default caches.
     */
    private const CACHES_TO_CLEAR = [
        InstallContext::CACHE_TAG_TEMPLATE,
        InstallContext::CACHE_TAG_CONFIG,
        InstallContext::CACHE_TAG_ROUTER,
        InstallContext::CACHE_TAG_PROXY,
        InstallContext::CACHE_TAG_HTTP,
    ];

    public function activate(ActivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    public function update(UpdateContext $context): void
    {
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    /**
     * The plugin stores no data of its own; Shopware removes the
     * configuration. Caches are cleared only if the plugin was active, as
     * recommended by the Shopware 5 plugin guidelines.
     */
    public function uninstall(UninstallContext $context): void
    {
        if ($context->getPlugin()->getActive()) {
            $context->scheduleClearCache(self::CACHES_TO_CLEAR);
        }
    }
}
