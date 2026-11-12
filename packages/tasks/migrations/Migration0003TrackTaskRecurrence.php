<?php

declare(strict_types=1);

namespace Kontor\Tasks\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

final class Migration0003TrackTaskRecurrence implements MigrationInterface
{
    public function component(): string
    {
        return 'tasks';
    }

    public function name(): string
    {
        return '0003_track_task_recurrence';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS kontor_task_recurrence (
                task_uid CHAR(26) NOT NULL,
                advanced SMALLINT NOT NULL,
                PRIMARY KEY (task_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_task_recurrence');
    }
}
