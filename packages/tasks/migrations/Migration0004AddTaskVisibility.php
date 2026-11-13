<?php

declare(strict_types=1);

namespace Kontor\Tasks\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

final class Migration0004AddTaskVisibility implements MigrationInterface
{
    public function component(): string
    {
        return 'tasks';
    }

    public function name(): string
    {
        return '0004_add_task_visibility';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            "ALTER TABLE kontor_tasks
                ADD visibility VARCHAR(20) NOT NULL DEFAULT 'organization',
                ADD department_role_id BIGINT UNSIGNED NULL,
                ADD INDEX idx_task_department (organization_id, department_role_id)"
        );
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE kontor_tasks DROP INDEX idx_task_department, DROP department_role_id, DROP visibility'
        );
    }
}
