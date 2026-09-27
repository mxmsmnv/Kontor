<?php

declare(strict_types=1);

namespace Kontor\Inventory\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#16.1 — followed exactly, including its absence of
 * created_at/updated_at/version/archived_at (unlike most tables,
 * kontor.md#10.4). `status` is this table's own lifecycle marker
 * ('active'|'inactive'), not archived_at.
 */
final class Migration0001CreateWarehousesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return '0001_create_warehouses_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_inventory_warehouses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                address_uid CHAR(26) NULL,
                manager_user_id BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                metadata_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_code (organization_id, code),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_inventory_warehouses');
    }
}
