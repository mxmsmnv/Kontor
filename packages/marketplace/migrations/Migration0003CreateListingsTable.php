<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "component metadata" milestone. One row per component known to a
 * registry — `manifest_json` is the full raw kontor.json entry
 * (kontor.md#22.1) as returned by that registry, so nothing discovered
 * about a component is lost even though only the fields this substage
 * actually surfaces (title/description/license/etc.) get their own
 * columns. Unique per `(registry_name, package)`: the same component can
 * legitimately appear in more than one registry.
 */
final class Migration0003CreateListingsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'marketplace';
    }

    public function name(): string
    {
        return '0003_create_listings_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_marketplace_listings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                registry_name VARCHAR(191) NOT NULL,
                package VARCHAR(191) NOT NULL,
                name VARCHAR(191) NOT NULL,
                version VARCHAR(32) NOT NULL,
                title VARCHAR(255) NULL,
                description TEXT NULL,
                license VARCHAR(50) NULL,
                repository_url VARCHAR(2048) NULL,
                publisher_name VARCHAR(191) NULL,
                manifest_json JSON NOT NULL,
                synced_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_registry_package (registry_name, package),
                INDEX idx_package (package)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_marketplace_listings');
    }
}
