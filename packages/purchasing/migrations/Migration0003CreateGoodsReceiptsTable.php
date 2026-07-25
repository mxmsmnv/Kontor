<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "goods receipt" milestone. A purchase order can be received across
 * several partial shipments, so this is its own append-only event table
 * rather than a single "received" flag on the order.
 */
final class Migration0003CreateGoodsReceiptsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'purchasing';
    }

    public function name(): string
    {
        return '0003_create_goods_receipts_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_purchasing_receipts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                purchase_order_uid CHAR(26) NOT NULL,
                warehouse_uid CHAR(26) NOT NULL,
                received_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_purchase_order (purchase_order_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_purchasing_receipts');
    }
}
