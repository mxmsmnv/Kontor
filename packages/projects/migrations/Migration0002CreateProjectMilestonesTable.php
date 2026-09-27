<?php

declare(strict_types=1);

namespace Kontor\Projects\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "milestones" substage milestone (a project's own checkpoints, not
 * this monorepo's own kontor.md#36 "substage milestones" terminology).
 */
final class Migration0002CreateProjectMilestonesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return '0002_create_project_milestones_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_project_milestones (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                project_uid CHAR(26) NOT NULL,
                name VARCHAR(255) NOT NULL,
                due_date DATE NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                completed_at DATETIME(6) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_project (project_uid, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_project_milestones');
    }
}
