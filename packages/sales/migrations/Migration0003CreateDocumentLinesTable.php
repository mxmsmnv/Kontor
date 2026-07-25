<?php

declare(strict_types=1);

namespace Kontor\Sales\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.4 — shared by quotations and orders now, and by invoices
 * (Substage 4.3) later, via the polymorphic document_type/document_uid
 * pair rather than a separate lines table per document type. No
 * created_at/updated_at columns, like kontor_contact_company and
 * kontor_catalog_prices before it — followed exactly.
 */
final class Migration0003CreateDocumentLinesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return '0003_create_document_lines_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_document_lines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(30) NOT NULL,
                document_uid CHAR(26) NOT NULL,
                item_uid CHAR(26) NULL,
                item_type VARCHAR(20) NULL,
                sku VARCHAR(100) NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                quantity_decimal DECIMAL(20,6) NOT NULL DEFAULT 1,
                unit_code VARCHAR(20) NOT NULL DEFAULT 'pcs',
                unit_price_minor BIGINT NOT NULL DEFAULT 0,
                currency_code CHAR(3) NOT NULL,
                discount_type VARCHAR(20) NULL,
                discount_value_decimal DECIMAL(20,6) NOT NULL DEFAULT 0,
                tax_code VARCHAR(30) NULL,
                tax_rate_decimal DECIMAL(10,6) NOT NULL DEFAULT 0,
                tax_minor BIGINT NOT NULL DEFAULT 0,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                total_minor BIGINT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                snapshot_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_document (document_type, document_uid, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_document_lines');
    }
}
