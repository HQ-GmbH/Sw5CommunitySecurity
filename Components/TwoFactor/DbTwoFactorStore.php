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
        `locked_until` DATETIME NULL,
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_sw5cs_backend_two_factor_user` FOREIGN KEY (`user_id`) REFERENCES `s_core_auth` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    /**
     * Column added in 0.4.2; tables of older versions get it on update.
     */
    public const ADD_LOCK_COLUMN = 'ALTER TABLE `' . self::TABLE . '` ADD COLUMN `locked_until` DATETIME NULL AFTER `failed_attempts`';

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
        $row = $this->db()->fetchRow(
            'SELECT `secret`, `last_used_step`, `failed_attempts`, UNIX_TIMESTAMP(`locked_until`) AS `locked_until` FROM `' . self::TABLE . '` WHERE `user_id` = ?',
            [$userId],
        );
        if (!\is_array($row) || !\is_string($row['secret'] ?? null)) {
            return null;
        }

        return new TwoFactorRecord(
            $row['secret'],
            is_numeric($row['last_used_step'] ?? null) ? (int) $row['last_used_step'] : null,
            is_numeric($row['failed_attempts'] ?? null) ? (int) $row['failed_attempts'] : 0,
            is_numeric($row['locked_until'] ?? null) ? (int) $row['locked_until'] : null,
        );
    }

    public function enable(int $userId, string $secret, int $usedStep): bool
    {
        // INSERT IGNORE: an existing set-up (primary key) is never replaced.
        $statement = $this->db()->query(
            'INSERT IGNORE INTO `' . self::TABLE . '` (`user_id`, `secret`, `last_used_step`, `failed_attempts`) VALUES (?, ?, ?, 0)',
            [$userId, $secret, $usedStep],
        );

        return $statement->rowCount() === 1;
    }

    public function markUsed(int $userId, int $step): bool
    {
        // The condition makes check and write one atomic step: of two
        // parallel requests with the same code only one gets a row.
        $statement = $this->db()->query(
            'UPDATE `' . self::TABLE . '` SET `last_used_step` = ?, `failed_attempts` = 0, `locked_until` = NULL'
            . ' WHERE `user_id` = ? AND (`last_used_step` IS NULL OR `last_used_step` < ?)',
            [$step, $userId, $step],
        );

        return $statement->rowCount() === 1;
    }

    public function reserveAttempt(int $userId, int $now): ?int
    {
        // MySQL evaluates the assignments from left to right, so the lock
        // uses the incremented counter. Timestamps come from PHP on both
        // sides (FROM_UNIXTIME/UNIX_TIMESTAMP), the database clock is not used.
        $statement = $this->db()->query(
            'UPDATE `' . self::TABLE . '` SET `failed_attempts` = `failed_attempts` + 1,'
            . ' `locked_until` = IF(`failed_attempts` >= ?, FROM_UNIXTIME(? + LEAST(`failed_attempts` * ?, ?)), NULL)'
            . ' WHERE `user_id` = ? AND (`locked_until` IS NULL OR `locked_until` <= FROM_UNIXTIME(?))',
            [TwoFactorLogin::LOCK_AFTER_FAILURES, $now, TwoFactorLogin::LOCK_SECONDS_PER_FAILURE, TwoFactorLogin::LOCK_SECONDS_MAX, $userId, $now],
        );
        if ($statement->rowCount() !== 1) {
            return null;
        }

        $attempts = $this->db()->fetchOne('SELECT `failed_attempts` FROM `' . self::TABLE . '` WHERE `user_id` = ?', [$userId]);

        return is_numeric($attempts) ? (int) $attempts : TwoFactorLogin::LOCK_AFTER_FAILURES;
    }

    /**
     * Adds the lock column to tables created by versions before 0.4.2.
     */
    public static function upgradeSchema(Enlight_Components_Db_Adapter_Pdo_Mysql $db): void
    {
        $column = $db->fetchOne('SHOW COLUMNS FROM `' . self::TABLE . "` LIKE 'locked_until'");
        if (!\is_string($column) || $column === '') {
            $db->query(self::ADD_LOCK_COLUMN);
        }
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
