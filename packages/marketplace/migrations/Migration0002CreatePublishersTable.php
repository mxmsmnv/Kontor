<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "publisher model" milestone. A publisher is derived from a
 * listing's manifest `author` field (kontor.md#22.1) and upserted by
 * name so every listing from the same author shares one identity rather
 * than duplicating it — `verified` becomes true the first time a
 * publisher is seen via a `trusted` registry sync (see
 * `RegistrySyncService`), and is never unset by a later untrusted sync.
 * Instance-wide, same reasoning as `kontor_marketplace_registries`.
 */
final class Migration0002CreatePublishersTable implements MigrationInterface
{
    public function component(): string
    {
        return 'marketplace';
    }

    public function name(): string
    {
        return '0002_create_publishers_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_marketplace_publishers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                url VARCHAR(2048) NULL,
                verified TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_marketplace_publishers');
    }
}
