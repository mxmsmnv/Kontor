<?php

declare(strict_types=1);

namespace Kontor\Sales\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.1. `workflow_state` is reserved for the future
 * KontorWorkflow component (section 18: "A component must provide safe
 * default workflows even if KontorWorkflow is not installed") — this
 * substage's actual transitions are enforced on `status` alone.
 */
final class Migration0001CreateQuotationsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return '0001_create_quotations_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_sales_quotations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NULL,
                customer_type VARCHAR(20) NOT NULL DEFAULT 'contact',
                customer_uid CHAR(26) NOT NULL,
                contact_uid CHAR(26) NULL,
                deal_uid CHAR(26) NULL,
                issue_date DATE NULL,
                valid_until DATE NULL,
                document_language CHAR(5) NOT NULL DEFAULT 'en',
                currency_code CHAR(3) NOT NULL,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                discount_minor BIGINT NOT NULL DEFAULT 0,
                tax_minor BIGINT NOT NULL DEFAULT 0,
                total_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                workflow_state VARCHAR(50) NULL,
                template_uid CHAR(26) NULL,
                snapshot_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                issued_at DATETIME(6) NULL,
                accepted_at DATETIME(6) NULL,
                rejected_at DATETIME(6) NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_org_number (organization_id, number),
                INDEX idx_organization_id (organization_id),
                INDEX idx_status (status),
                INDEX idx_customer (customer_type, customer_uid),
                INDEX idx_deal_uid (deal_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_sales_quotations');
    }
}
