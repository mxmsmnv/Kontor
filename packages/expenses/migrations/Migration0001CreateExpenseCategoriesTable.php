<?php

declare(strict_types=1);

namespace Kontor\Expenses\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section, permission list, or workflow diagram for
 * Expenses in kontor.md — full gap-fill, same situation Tasks/
 * Collaboration/Dashboard/Reports/Purchasing were in. Unlike
 * kontor/catalog's UnitOfMeasure/TaxCode (fixed in-memory registries),
 * expense categories are organization-specific and user-managed, so this
 * is a real table rather than a registry class.
 */
final class Migration0001CreateExpenseCategoriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'expenses';
    }

    public function name(): string
    {
        return '0001_create_expense_categories_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_expense_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_code (organization_id, code),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_expense_categories');
    }
}
