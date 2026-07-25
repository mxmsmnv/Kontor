<?php

declare(strict_types=1);

namespace Kontor\Reports\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section for Reports in kontor.md (sections 11-16
 * stop at Inventory) — same gap-fill situation as Tasks/Collaboration/
 * Dashboard before it. `recurrence_rule` reuses the same small named set
 * ('daily'|'weekly'|'monthly'|'yearly') kontor/tasks uses for its own
 * recurrence, not a full RFC 5545 RRULE.
 */
final class Migration0001CreateScheduledReportsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'reports';
    }

    public function name(): string
    {
        return '0001_create_scheduled_reports_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_scheduled_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                provider_key VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                filters_json JSON NULL,
                group_by_json JSON NULL,
                format VARCHAR(10) NOT NULL DEFAULT 'csv',
                recurrence_rule VARCHAR(20) NOT NULL,
                next_run_at DATETIME(6) NOT NULL,
                last_run_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_due (organization_id, next_run_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_scheduled_reports');
    }
}
