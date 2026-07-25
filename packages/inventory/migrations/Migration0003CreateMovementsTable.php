<?php

declare(strict_types=1);

namespace Kontor\Inventory\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#16.3 — followed exactly: no updated_at/version/archived_at, an
 * append-only movement ledger is never edited after the fact. `idempotency_key`
 * gets its own unique index (kontor.md#20.9's idempotency-key semantics,
 * first used by kontor/queue's JobRepository) — MySQL treats multiple NULLs
 * in a UNIQUE index as distinct, so movements that don't supply one never
 * collide with each other.
 */
final class Migration0003CreateMovementsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return '0003_create_movements_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_inventory_movements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                movement_type VARCHAR(20) NOT NULL,
                item_uid CHAR(26) NOT NULL,
                source_warehouse_uid CHAR(26) NULL,
                destination_warehouse_uid CHAR(26) NULL,
                quantity DECIMAL(20,6) NOT NULL,
                unit_code VARCHAR(20) NOT NULL DEFAULT 'pcs',
                reference_type VARCHAR(30) NULL,
                reference_uid CHAR(26) NULL,
                reason VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'completed',
                occurred_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                idempotency_key VARCHAR(191) NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_idempotency (organization_id, idempotency_key),
                INDEX idx_organization_id (organization_id),
                INDEX idx_item (organization_id, item_uid, occurred_at),
                INDEX idx_reference (reference_type, reference_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_inventory_movements');
    }
}
