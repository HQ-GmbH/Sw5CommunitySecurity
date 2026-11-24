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

/**
 * Plugin configuration of the default shop, for settings that act on
 * resources shared by all shops (e.g. compiled templates).
 */
interface DefaultShopConfigSource
{
    public function getForDefaultShop(): PluginConfig;
}
