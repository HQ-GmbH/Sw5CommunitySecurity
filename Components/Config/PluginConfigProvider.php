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

namespace Sw5CommunitySecurity\Components\Config;

use Shopware\Components\Plugin\Configuration\ReaderInterface;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reads the plugin configuration for the shop handling the current request.
 *
 * The "shop" service only exists once Shopware has resolved the shop for a
 * frontend or widget request. Without it (backend, CLI) the configuration
 * falls back to the default values.
 */
final class PluginConfigProvider
{
    public function __construct(
        private readonly ReaderInterface $reader,
        private readonly ContainerInterface $container,
        private readonly string $pluginName,
    ) {
    }

    public function getForCurrentShop(): PluginConfig
    {
        return PluginConfig::fromArray(
            $this->reader->getByPluginName($this->pluginName, $this->resolveShopId())
        );
    }

    private function resolveShopId(): ?int
    {
        if (!$this->container->initialized('shop')) {
            return null;
        }

        $shop = $this->container->get('shop');

        return $shop instanceof Shop ? $shop->getId() : null;
    }
}
