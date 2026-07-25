<?php

declare(strict_types=1);

namespace Kontor\Tasks\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section for Tasks in kontor.md (sections 11-16 stop
 * at Inventory) — same gap-fill situation as Cache, Search and Documents
 * before it. `due_at`/`start_at` back the "calendar" milestone (there's no
 * separate calendar entity — a calendar view is just tasks queried by
 * date, kontor.md having no admin UI to build this substage anyway).
 * `recurrence_rule` is a small named set ('daily'|'weekly'|'monthly'|
 * 'yearly'), not a full RFC 5545 RRULE — same "don't build more than the
 * milestone needs" choice as SequenceService's reset_policy.
 */
final class Migration0001CreateTasksTable implements MigrationInterface
{
    public function component(): string
    {
        return 'tasks';
    }

    public function name(): string
    {
        return '0001_create_tasks_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_tasks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                priority VARCHAR(10) NOT NULL DEFAULT 'normal',
                assigned_to BIGINT UNSIGNED NULL,
                start_at DATETIME(6) NULL,
                due_at DATETIME(6) NULL,
                completed_at DATETIME(6) NULL,
                recurrence_rule VARCHAR(20) NULL,
                recurrence_until DATE NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_due_at (organization_id, due_at),
                INDEX idx_assigned_to (assigned_to)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_tasks');
    }
}
