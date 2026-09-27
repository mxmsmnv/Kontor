<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "official registry"/"custom registry" milestones. No dedicated
 * schema section in kontor.md for Marketplace — full gap-fill. Instance-
 * wide, not tenant-scoped — same shape as kontor_components
 * (kontor.md#11.2): which components are available to *this Kontor
 * installation* isn't a per-organization concern, so there's no
 * `organization_id` column and no `uid`, addressed by `name` instead,
 * exactly like `kontor_components`. `trusted` distinguishes the official
 * registry (always trusted) from a self-hosted/third-party custom
 * registry (not trusted unless explicitly marked) — see
 * `RegistrySyncService`'s use of it for the "publisher model" milestone.
 */
final class Migration0001CreateRegistriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'marketplace';
    }

    public function name(): string
    {
        return '0001_create_registries_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_marketplace_registries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'custom',
                trusted TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                last_synced_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_marketplace_registries');
    }
}
