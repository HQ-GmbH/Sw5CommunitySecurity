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

namespace Sw5CommunitySecurity\Components\Compat\Sw57;

use Shopware\Components\Plugin\Configuration\ReaderInterface;
use Shopware\Models\Shop\Shop;
use Sw5CommunitySecurity\Components\Config\ConfigReader;

/**
 * Shopware 5.7: Shopware\Components\Plugin\Configuration\ReaderInterface
 * (engine/Shopware/Components/Plugin/Configuration/ReaderInterface.php).
 */
final class PluginConfigReader implements ConfigReader
{
    /**
     * @readonly
     */
    private ReaderInterface $reader;

    public function __construct(ReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    public function read(string $pluginName, ?Shop $shop): array
    {
        return $this->reader->getByPluginName($pluginName, $shop !== null ? $shop->getId() : null);
    }
}
