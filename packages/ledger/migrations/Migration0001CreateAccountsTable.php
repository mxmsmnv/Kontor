<?php

declare(strict_types=1);

namespace Kontor\Ledger\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "chart of accounts" milestone. No dedicated schema section in
 * kontor.md for Ledger — full gap-fill. `parent_uid` gives accounts a
 * hierarchy (e.g. a "1000 — Assets" parent with child accounts under
 * it); `type` is the classic five-way double-entry classification, used
 * by `AccountBalanceService` to decide an account's normal balance side.
 * Standard columns apply in full.
 */
final class Migration0001CreateAccountsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'ledger';
    }

    public function name(): string
    {
        return '0001_create_accounts_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_ledger_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL,
                parent_uid CHAR(26) NULL,
                currency_code CHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_org_code (organization_id, code),
                INDEX idx_organization_id (organization_id),
                INDEX idx_parent_uid (parent_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_ledger_accounts');
    }
}
