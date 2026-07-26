<?php

declare(strict_types=1);

namespace Kontor\Ledger\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "double-entry foundations" milestone: one row per journal entry.
 * Immutable once recorded — a correction is a new, reversing entry, never
 * an edit — so no `version`/`archived_at`, the same reasoning
 * `kontor/automation`'s execution log already used for its own
 * append-only table. `reference_type`/`reference_uid` loosely point at
 * whatever business document caused this entry (an invoice, a payment,
 * …), the same "loose reference" convention `kontor/expenses`' own
 * `receipt_file_uid` already uses.
 */
final class Migration0002CreateEntriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'ledger';
    }

    public function name(): string
    {
        return '0002_create_entries_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_ledger_entries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(500) NOT NULL,
                entry_date DATE NOT NULL,
                reference_type VARCHAR(50) NULL,
                reference_uid CHAR(26) NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entry_date (organization_id, entry_date),
                INDEX idx_reference (reference_type, reference_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_ledger_entries');
    }
}
