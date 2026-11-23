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

namespace Sw5CommunitySecurity\Components\Messages;

/**
 * Source of the texts shown to backend users. Kept free of Shopware
 * dependencies so the guards remain unit-testable.
 */
interface MessageCatalog
{
    /**
     * Returns the text for the given key, or the default if none is available.
     */
    public function get(string $key, string $default): string;
}
