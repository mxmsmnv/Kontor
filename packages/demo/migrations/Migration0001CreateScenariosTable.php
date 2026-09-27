<?php

declare(strict_types=1);

namespace Kontor\Demo\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

final class Migration0001CreateScenariosTable implements MigrationInterface
{
    public function component(): string
    {
        return 'demo';
    }

    public function name(): string
    {
        return '0001_create_scenarios_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_demo_scenarios (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                current_state VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL,
                workflow_instance_uid CHAR(26) NULL,
                pending_approval_uid CHAR(26) NULL,
                entities_json JSON NOT NULL,
                last_error TEXT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_state (organization_id, current_state),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_demo_scenarios');
    }
}
