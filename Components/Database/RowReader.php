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

namespace Sw5CommunitySecurity\Components\Database;

/**
 * Read-only database access. Shopware 5.6 ships Doctrine DBAL 2.6, Shopware
 * 5.7 DBAL 2.13 with a different fetch API; the matching implementation is
 * selected in Sw5CommunitySecurity::build().
 */
interface RowReader
{
    /**
     * @param array<int|string, mixed>      $params
     * @param array<int|string, int|string> $types
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = [], array $types = []): array;
}
