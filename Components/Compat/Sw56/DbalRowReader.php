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

use Doctrine\DBAL\Connection;
use Sw5CommunitySecurity\Components\Database\RowReader;

/**
 * Shopware 5.6: Doctrine DBAL 2.6, Connection::fetchAll().
 */
final class DbalRowReader implements RowReader
{
    /**
     * @readonly
     */
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function fetchAll(string $sql, array $params = [], array $types = []): array
    {
        $rows = [];
        foreach ($this->connection->fetchAll($sql, $params, $types) as $row) {
            if (\is_array($row)) {
                $rows[] = array_filter($row, static function ($key): bool {
                    return \is_string($key);
                }, \ARRAY_FILTER_USE_KEY);
            }
        }

        return $rows;
    }
}
