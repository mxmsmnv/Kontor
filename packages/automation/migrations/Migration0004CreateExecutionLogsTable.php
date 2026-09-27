<?php

declare(strict_types=1);

namespace Kontor\Automation\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "logs" milestone: one row per rule evaluation (whether its
 * conditions matched or not, and whether it was a dry run), append-only —
 * same shape as kontor/inventory's movement ledger and kontor/workflow's
 * history table.
 */
final class Migration0004CreateExecutionLogsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'automation';
    }

    public function name(): string
    {
        return '0004_create_execution_logs_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_automation_execution_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                rule_uid CHAR(26) NULL,
                trigger_event VARCHAR(150) NOT NULL,
                matched TINYINT(1) NOT NULL,
                dry_run TINYINT(1) NOT NULL DEFAULT 0,
                recursion_blocked TINYINT(1) NOT NULL DEFAULT 0,
                actions_result_json JSON NULL,
                error VARCHAR(500) NULL,
                occurred_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_rule (rule_uid, occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_automation_execution_logs');
    }
}
