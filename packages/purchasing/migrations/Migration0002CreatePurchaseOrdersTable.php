<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * `warehouse_uid` is the destination goods receipt targets by default —
 * the "inventory integration" milestone's own link between the two
 * packages. Lines are shared kontor_document_lines rows
 * (document_type = 'purchase_order'), same reuse kontor/invoices already
 * established for kontor/sales' table.
 */
final class Migration0002CreatePurchaseOrdersTable implements MigrationInterface
{
    public function component(): string
    {
        return 'purchasing';
    }

    public function name(): string
    {
        return '0002_create_purchase_orders_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_purchasing_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NULL,
                supplier_uid CHAR(26) NOT NULL,
                warehouse_uid CHAR(26) NULL,
                issue_date DATE NULL,
                expected_date DATE NULL,
                currency_code CHAR(3) NOT NULL,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                discount_minor BIGINT NOT NULL DEFAULT 0,
                tax_minor BIGINT NOT NULL DEFAULT 0,
                total_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                issued_at DATETIME(6) NULL,
                received_at DATETIME(6) NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_supplier (supplier_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_purchasing_orders');
    }
}
