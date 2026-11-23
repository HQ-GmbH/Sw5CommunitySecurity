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
    /**
     * @readonly
     */
    private ConfigReader $reader;

    /**
     * @readonly
     */
    private ContainerInterface $container;

    /**
     * @readonly
     */
    private string $pluginName;

    public function __construct(ConfigReader $reader, ContainerInterface $container, string $pluginName)
    {
        $this->reader = $reader;
        $this->container = $container;
        $this->pluginName = $pluginName;
    }

    public function getForCurrentShop(): PluginConfig
    {
        $raw = [];
        foreach ($this->reader->read($this->pluginName, $this->resolveShop()) as $name => $value) {
            if (\is_string($name)) {
                $raw[$name] = $value;
            }
        }

        return PluginConfig::fromArray($raw);
    }

    private function resolveShop(): ?Shop
    {
        if (!$this->container->initialized('shop')) {
            return null;
        }

        $shop = $this->container->get('shop');

        return $shop instanceof Shop ? $shop : null;
    }
}
