<?php

declare(strict_types=1);

namespace Kontor\Expenses\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * `receipt_file_uid` is a loose reference to a kontor/files row
 * (kontor.md#10.7: "prefer stable IDs over direct foreign keys") — this
 * package has no hard dependency on kontor/files, the same looseness
 * kontor_document_lines.item_uid already has toward kontor/catalog.
 * `supplier_uid` is similarly loose toward kontor/purchasing.
 */
final class Migration0002CreateExpensesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'expenses';
    }

    public function name(): string
    {
        return '0002_create_expenses_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_expenses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                category_uid CHAR(26) NOT NULL,
                supplier_uid CHAR(26) NULL,
                description VARCHAR(255) NOT NULL,
                amount_minor BIGINT NOT NULL,
                currency_code CHAR(3) NOT NULL,
                expense_date DATE NOT NULL,
                receipt_file_uid CHAR(26) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                submitted_by BIGINT UNSIGNED NULL,
                approved_by BIGINT UNSIGNED NULL,
                submitted_at DATETIME(6) NULL,
                approved_at DATETIME(6) NULL,
                rejected_at DATETIME(6) NULL,
                reimbursed_at DATETIME(6) NULL,
                rejection_reason VARCHAR(255) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_category (category_uid),
                INDEX idx_status (organization_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_expenses');
    }
}
