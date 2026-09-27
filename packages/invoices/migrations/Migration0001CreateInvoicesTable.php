<?php

declare(strict_types=1);

namespace Kontor\Invoices\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#15.3, plus two gap-fill columns the spec's column list doesn't
 * have: `kind` and `credited_invoice_uid`. kontor.md#19.6 gives credit
 * notes their own permissions (kontor-invoices-credit-note-create/-issue)
 * and diagram 17.2 gives them their own transition ("Paid --> Credited:
 * credit note"), but section 15 has no separate credit-note table or
 * column to tell one apart from a regular invoice — a credit note is
 * modeled as another kontor_invoices row (kind = 'credit_note') pointing
 * back at the invoice it credits, reusing the exact same numbering,
 * document-lines and rendering machinery as an ordinary invoice, rather
 * than duplicating all of that into a parallel table.
 */
final class Migration0001CreateInvoicesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'invoices';
    }

    public function name(): string
    {
        return '0001_create_invoices_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_invoices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NULL,
                kind VARCHAR(20) NOT NULL DEFAULT 'invoice',
                credited_invoice_uid CHAR(26) NULL,
                customer_type VARCHAR(20) NOT NULL,
                customer_uid CHAR(26) NOT NULL,
                contact_uid CHAR(26) NULL,
                order_uid CHAR(26) NULL,
                issue_date DATE NULL,
                due_date DATE NULL,
                document_language VARCHAR(10) NOT NULL DEFAULT 'en',
                currency_code CHAR(3) NOT NULL,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                discount_minor BIGINT NOT NULL DEFAULT 0,
                tax_minor BIGINT NOT NULL DEFAULT 0,
                total_minor BIGINT NOT NULL DEFAULT 0,
                paid_minor BIGINT NOT NULL DEFAULT 0,
                due_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                workflow_state VARCHAR(50) NULL,
                template_uid CHAR(26) NULL,
                snapshot_json JSON NULL,
                issued_at DATETIME(6) NULL,
                sent_at DATETIME(6) NULL,
                paid_at DATETIME(6) NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_overdue_sweep (organization_id, status, due_date),
                INDEX idx_credited_invoice (credited_invoice_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_invoices');
    }
}
