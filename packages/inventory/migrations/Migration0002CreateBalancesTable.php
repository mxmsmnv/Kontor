<?php

declare(strict_types=1);

namespace Kontor\Inventory\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#16.2 — followed exactly: no `uid`, no `created_at` (a balance
 * row's identity is the (organization, warehouse, item) triple itself, and
 * it always exists implicitly at zero before its first movement — there's
 * no meaningful "created" moment to record). `quantity_available` is a
 * real stored column, not computed at query time, per the schema — kept
 * in sync with on_hand - reserved on every write (InventoryMovementService).
 */
final class Migration0002CreateBalancesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return '0002_create_balances_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_inventory_balances (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                warehouse_uid CHAR(26) NOT NULL,
                item_uid CHAR(26) NOT NULL,
                quantity_on_hand DECIMAL(20,6) NOT NULL DEFAULT 0,
                quantity_reserved DECIMAL(20,6) NOT NULL DEFAULT 0,
                quantity_available DECIMAL(20,6) NOT NULL DEFAULT 0,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_balance (organization_id, warehouse_uid, item_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_inventory_balances');
    }
}
