<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * A lighter junction-style table, like kontor_payment_allocations — no
 * updated_at/version/archived_at. `po_line_uid` points at a
 * kontor_document_lines row; summing quantity_received across every
 * receipt line for a given po_line_uid is how
 * PurchaseOrderRepository/GoodsReceiptService tell how much of that line
 * has been received so far, without touching kontor/inventory's own
 * movement ledger for a purchasing-side question.
 */
final class Migration0004CreateGoodsReceiptLinesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'purchasing';
    }

    public function name(): string
    {
        return '0004_create_goods_receipt_lines_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_purchasing_receipt_lines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                receipt_uid CHAR(26) NOT NULL,
                po_line_uid CHAR(26) NOT NULL,
                item_uid CHAR(26) NOT NULL,
                quantity_received DECIMAL(20,6) NOT NULL,
                unit_code VARCHAR(20) NOT NULL DEFAULT 'pcs',
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_receipt (receipt_uid),
                INDEX idx_po_line (po_line_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_purchasing_receipt_lines');
    }
}
