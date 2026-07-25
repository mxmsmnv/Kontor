<?php

declare(strict_types=1);

namespace Kontor\Inventory\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "barcode support" milestone: kontor.md#16 has no schema for it —
 * this is this package's own gap-fill. Kept intentionally minimal (a
 * lookup table, not a rich barcode entity): a scanned barcode resolves to
 * an item_uid, for a caller to then pass into InventoryMovementService.
 * item_uid stays a loose reference (kontor.md#10.7) — this package has no
 * hard dependency on kontor/catalog to validate it exists.
 */
final class Migration0004CreateBarcodesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return '0004_create_barcodes_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_inventory_barcodes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                barcode VARCHAR(64) NOT NULL,
                item_uid CHAR(26) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_barcode (organization_id, barcode),
                INDEX idx_item (organization_id, item_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_inventory_barcodes');
    }
}
