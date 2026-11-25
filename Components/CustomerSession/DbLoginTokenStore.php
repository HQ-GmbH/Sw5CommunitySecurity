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

namespace Sw5CommunitySecurity\Components\CustomerSession;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores token hashes in the plugin's own table (created on install and
 * update, dropped on uninstall unless the data is kept). The Zend database
 * adapter is used because its API is identical in Shopware 5.6 and 5.7. It
 * is fetched on first use: the store belongs to an event subscriber that
 * Shopware may instantiate while the "db" service itself is being created.
 */
final class DbLoginTokenStore implements LoginTokenStore
{
    public const TABLE = 's_plugin_sw5cs_customer_login';

    /**
     * Rows are removed together with the customer account.
     */
    public const CREATE_TABLE = 'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
        `user_id` INT(11) NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_sw5cs_customer_login_user` FOREIGN KEY (`user_id`) REFERENCES `s_user` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public const DROP_TABLE = 'DROP TABLE IF EXISTS `' . self::TABLE . '`';

    /**
     * @readonly
     */
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function find(int $customerId): ?string
    {
        $hash = $this->db()->fetchOne('SELECT `token_hash` FROM `' . self::TABLE . '` WHERE `user_id` = ?', [$customerId]);

        return \is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function save(int $customerId, string $tokenHash): void
    {
        $this->db()->query(
            'INSERT INTO `' . self::TABLE . '` (`user_id`, `token_hash`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `token_hash` = VALUES(`token_hash`)',
            [$customerId, $tokenHash],
        );
    }

    private function db(): Enlight_Components_Db_Adapter_Pdo_Mysql
    {
        $db = $this->container->get('db');
        if (!$db instanceof Enlight_Components_Db_Adapter_Pdo_Mysql) {
            throw new RuntimeException('The Shopware database adapter is not available.');
        }

        return $db;
    }
}
