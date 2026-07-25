<?php

declare(strict_types=1);

namespace Kontor\Payments\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.5. `status` isn't spec'd as an enum with a fixed value set
 * (unlike e.g. the invoice lifecycle, which has a Mermaid diagram) — this
 * package uses 'draft' | 'confirmed' | 'reversed', matching the
 * kontor-payments-payment-edit-draft permission (kontor.md#19.7): a draft
 * payment is still editable, confirming it is what makes it allocatable.
 */
final class Migration0001CreatePaymentsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'payments';
    }

    public function name(): string
    {
        return '0001_create_payments_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NULL,
                payer_type VARCHAR(20) NOT NULL,
                payer_uid CHAR(26) NOT NULL,
                payment_date DATE NULL,
                amount_minor BIGINT NOT NULL,
                currency_code CHAR(3) NOT NULL,
                method VARCHAR(30) NOT NULL DEFAULT 'other',
                transaction_reference VARCHAR(191) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                external_id VARCHAR(191) NULL,
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_payer (payer_type, payer_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_payments');
    }
}
