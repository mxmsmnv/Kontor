<?php

declare(strict_types=1);

namespace Kontor\Ledger\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The other half of "double-entry foundations": one row per debit or
 * credit leg of an entry. `LedgerEntryService::record()` is the only
 * place these are ever written, and it enforces the one rule that makes
 * this double-entry at all — every entry's debit lines and credit lines
 * must sum to the same amount, per currency, before anything is
 * persisted. Immutable once recorded, same reasoning as
 * `kontor_ledger_entries`.
 */
final class Migration0003CreateLinesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'ledger';
    }

    public function name(): string
    {
        return '0003_create_lines_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_ledger_lines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                entry_uid CHAR(26) NOT NULL,
                account_uid CHAR(26) NOT NULL,
                debit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                credit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency_code CHAR(3) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entry_uid (entry_uid),
                INDEX idx_account_uid (account_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_ledger_lines');
    }
}
