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

namespace Sw5CommunitySecurity\Components\Compat\Sw56;

use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;
use Sw5CommunitySecurity\Components\Config\ConfigReader;

/**
 * Shopware 5.6: Shopware\Components\Plugin\CachedConfigReader
 * (engine/Shopware/Components/Plugin/CachedConfigReader.php in v5.6.10).
 */
final class PluginConfigReader implements ConfigReader
{
    /**
     * @readonly
     */
    private CachedConfigReader $reader;

    public function __construct(CachedConfigReader $reader)
    {
        $this->reader = $reader;
    }

    public function read(string $pluginName, ?Shop $shop): array
    {
        return $this->reader->getByPluginName($pluginName, $shop);
    }
}
