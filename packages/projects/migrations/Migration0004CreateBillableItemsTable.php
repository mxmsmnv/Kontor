<?php

declare(strict_types=1);

namespace Kontor\Projects\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "billable items" milestone — flat, non-time-based charges on a
 * project (e.g. "software license fee"). `invoice_line_uid` mirrors
 * kontor_project_time_entries' own use of it for the "invoicing
 * integration" milestone.
 */
final class Migration0004CreateBillableItemsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return '0004_create_billable_items_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_project_billable_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                project_uid CHAR(26) NOT NULL,
                milestone_uid CHAR(26) NULL,
                description VARCHAR(255) NOT NULL,
                quantity_decimal DECIMAL(20,6) NOT NULL DEFAULT 1,
                unit_price_minor BIGINT NOT NULL,
                currency_code CHAR(3) NOT NULL,
                invoice_line_uid CHAR(26) NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_project (project_uid),
                INDEX idx_uninvoiced (project_uid, invoice_line_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_project_billable_items');
    }
}
