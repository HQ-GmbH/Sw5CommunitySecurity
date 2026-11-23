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
 * Returns the built-in bilingual defaults; used without Shopware (unit tests).
 */
final class DefaultMessageCatalog implements MessageCatalog
{
    public function get(string $key, string $default): string
    {
        return $default;
    }
}
