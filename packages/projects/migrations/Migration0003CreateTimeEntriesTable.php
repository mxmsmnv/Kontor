<?php

declare(strict_types=1);

namespace Kontor\Projects\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "time tracking" milestone. `ended_at` is NULL while a timer is
 * running — TimeTrackingService::start()/stop() is the actual timer;
 * logManual() fills both directly for a retroactively-entered duration.
 * `invoice_line_uid` is set once TimeTrackingService::stop()'s entry has
 * been pulled into an invoice by ProjectInvoicingService (the "invoicing
 * integration" milestone) — a loose reference to a kontor_document_lines
 * row, not a hard dependency.
 */
final class Migration0003CreateTimeEntriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return '0003_create_time_entries_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_project_time_entries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                project_uid CHAR(26) NOT NULL,
                milestone_uid CHAR(26) NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) NULL,
                started_at DATETIME(6) NOT NULL,
                ended_at DATETIME(6) NULL,
                duration_minutes INT UNSIGNED NULL,
                billable TINYINT(1) NOT NULL DEFAULT 1,
                hourly_rate_minor BIGINT NULL,
                currency_code CHAR(3) NULL,
                invoice_line_uid CHAR(26) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_project (project_uid),
                INDEX idx_uninvoiced (project_uid, billable, invoice_line_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_project_time_entries');
    }
}
