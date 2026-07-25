<?php

declare(strict_types=1);

namespace Kontor\Tasks\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "reminders" milestone: a task can have more than one reminder (e.g.
 * a day before and an hour before), so this is its own table rather than a
 * single remind_at column on kontor_tasks. A lighter junction-style table
 * like kontor_payment_allocations — no version/archived_at, sent_at is its
 * own lifecycle marker.
 */
final class Migration0002CreateTaskRemindersTable implements MigrationInterface
{
    public function component(): string
    {
        return 'tasks';
    }

    public function name(): string
    {
        return '0002_create_task_reminders_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_task_reminders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                task_uid CHAR(26) NOT NULL,
                remind_at DATETIME(6) NOT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'app',
                sent_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_task (task_uid),
                INDEX idx_due_reminders (organization_id, remind_at, sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_task_reminders');
    }
}
