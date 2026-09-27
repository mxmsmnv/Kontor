<?php

declare(strict_types=1);

namespace Kontor\Payments\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.6. Unlike kontor_document_lines (no uid/created_at at all),
 * this table has a uid but no created_at/updated_at/version/archived_at —
 * followed exactly as specified rather than "improved" to match every
 * other table's standard columns (kontor.md#10.3). `allocated_at` already
 * serves as this row's creation timestamp; `reversed_at` being NULL is
 * this table's own archived_at-equivalent for the "reversals" milestone.
 */
final class Migration0002CreatePaymentAllocationsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'payments';
    }

    public function name(): string
    {
        return '0002_create_payment_allocations_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_payment_allocations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                payment_uid CHAR(26) NOT NULL,
                document_type VARCHAR(30) NOT NULL,
                document_uid CHAR(26) NOT NULL,
                amount_minor BIGINT NOT NULL,
                currency_code CHAR(3) NOT NULL,
                allocated_at DATETIME(6) NOT NULL,
                reversed_at DATETIME(6) NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_payment (payment_uid),
                INDEX idx_document (document_type, document_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_payment_allocations');
    }
}
