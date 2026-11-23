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

/**
 * Reads the stored plugin configuration. Shopware 5.6 and 5.7 provide
 * different reader services; the matching implementation is selected in
 * Sw5CommunitySecurity::build().
 */
interface ConfigReader
{
    /**
     * @return array<array-key, mixed>
     */
    public function read(string $pluginName, ?Shop $shop): array;
}
