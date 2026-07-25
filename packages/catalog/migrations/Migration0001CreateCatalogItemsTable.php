<?php

declare(strict_types=1);

namespace Kontor\Catalog\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#14.1. "products" and "services" (Substage 3.2 milestones) are
 * both rows here, distinguished by item_type — the spec defines one items
 * table, not separate ones. Note there is no deleted_at column here (unlike
 * kontor_contacts/kontor_companies) — followed exactly as given.
 */
final class Migration0001CreateCatalogItemsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'catalog';
    }

    public function name(): string
    {
        return '0001_create_catalog_items_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_catalog_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                item_type VARCHAR(20) NOT NULL DEFAULT 'product',
                sku VARCHAR(100) NULL,
                barcode VARCHAR(100) NULL,
                title_json JSON NOT NULL,
                description_json JSON NULL,
                category_uid CHAR(26) NULL,
                unit_code VARCHAR(20) NOT NULL DEFAULT 'pcs',
                tax_code VARCHAR(30) NULL,
                sales_price_minor BIGINT NULL,
                sales_currency CHAR(3) NULL,
                purchase_price_minor BIGINT NULL,
                purchase_currency CHAR(3) NULL,
                cost_price_minor BIGINT NULL,
                cost_currency CHAR(3) NULL,
                track_inventory TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_org_sku (organization_id, sku),
                INDEX idx_organization_id (organization_id),
                INDEX idx_category_uid (category_uid),
                INDEX idx_status (status),
                INDEX idx_item_type (item_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_catalog_items');
    }
}
