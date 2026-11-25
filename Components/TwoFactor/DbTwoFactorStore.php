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

namespace Sw5CommunitySecurity\Components\TwoFactor;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin table for the two-factor settings, created on install and update
 * and dropped on uninstall unless the data is kept. The secret is stored as
 * entered in the app (Base32); anyone with database access could read it,
 * like the password hashes in the same database. The database adapter is
 * fetched on first use (see DbLoginTokenStore).
 */
final class DbTwoFactorStore implements TwoFactorStore
{
    public const TABLE = 's_plugin_sw5cs_backend_two_factor';

    public const CREATE_TABLE = 'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
        `user_id` INT(11) NOT NULL,
        `secret` VARCHAR(64) NOT NULL,
        `last_used_step` BIGINT NULL,
        `failed_attempts` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_sw5cs_backend_two_factor_user` FOREIGN KEY (`user_id`) REFERENCES `s_core_auth` (`id`) ON DELETE CASCADE
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

    public function find(int $userId): ?TwoFactorRecord
    {
        $row = $this->db()->fetchRow('SELECT `secret`, `last_used_step`, `failed_attempts` FROM `' . self::TABLE . '` WHERE `user_id` = ?', [$userId]);
        if (!\is_array($row) || !\is_string($row['secret'] ?? null)) {
            return null;
        }

        return new TwoFactorRecord(
            $row['secret'],
            is_numeric($row['last_used_step'] ?? null) ? (int) $row['last_used_step'] : null,
            is_numeric($row['failed_attempts'] ?? null) ? (int) $row['failed_attempts'] : 0,
        );
    }

    public function enable(int $userId, string $secret, int $usedStep): void
    {
        $this->db()->query(
            'INSERT INTO `' . self::TABLE . '` (`user_id`, `secret`, `last_used_step`, `failed_attempts`) VALUES (?, ?, ?, 0)'
            . ' ON DUPLICATE KEY UPDATE `secret` = VALUES(`secret`), `last_used_step` = VALUES(`last_used_step`), `failed_attempts` = 0',
            [$userId, $secret, $usedStep],
        );
    }

    public function markUsed(int $userId, int $step): void
    {
        $this->db()->query('UPDATE `' . self::TABLE . '` SET `last_used_step` = ?, `failed_attempts` = 0 WHERE `user_id` = ?', [$step, $userId]);
    }

    public function recordFailure(int $userId): int
    {
        $this->db()->query('UPDATE `' . self::TABLE . '` SET `failed_attempts` = `failed_attempts` + 1 WHERE `user_id` = ?', [$userId]);
        $failures = $this->db()->fetchOne('SELECT `failed_attempts` FROM `' . self::TABLE . '` WHERE `user_id` = ?', [$userId]);

        return is_numeric($failures) ? (int) $failures : 0;
    }

    public function disable(int $userId): void
    {
        $this->db()->query('DELETE FROM `' . self::TABLE . '` WHERE `user_id` = ?', [$userId]);
    }

    public function enabledUserIds(): array
    {
        $ids = [];
        foreach ($this->db()->fetchCol('SELECT `user_id` FROM `' . self::TABLE . '` ORDER BY `user_id`') as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
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
