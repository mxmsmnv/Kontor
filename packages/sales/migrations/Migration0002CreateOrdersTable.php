<?php

declare(strict_types=1);

namespace Kontor\Sales\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.2
 */
final class Migration0002CreateOrdersTable implements MigrationInterface
{
    public function component(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return '0002_create_orders_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_sales_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NULL,
                customer_type VARCHAR(20) NOT NULL DEFAULT 'contact',
                customer_uid CHAR(26) NOT NULL,
                contact_uid CHAR(26) NULL,
                quotation_uid CHAR(26) NULL,
                issue_date DATE NULL,
                expected_delivery_date DATE NULL,
                currency_code CHAR(3) NOT NULL,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                discount_minor BIGINT NOT NULL DEFAULT 0,
                tax_minor BIGINT NOT NULL DEFAULT 0,
                shipping_minor BIGINT NOT NULL DEFAULT 0,
                total_minor BIGINT NOT NULL DEFAULT 0,
                order_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
                fulfillment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                snapshot_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                confirmed_at DATETIME(6) NULL,
                completed_at DATETIME(6) NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_org_number (organization_id, number),
                INDEX idx_organization_id (organization_id),
                INDEX idx_order_status (order_status),
                INDEX idx_customer (customer_type, customer_uid),
                INDEX idx_quotation_uid (quotation_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_sales_orders');
    }
}
