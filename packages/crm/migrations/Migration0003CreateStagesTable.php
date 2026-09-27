<?php

declare(strict_types=1);

namespace Kontor\CRM\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#13.3
 */
final class Migration0003CreateStagesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'crm';
    }

    public function name(): string
    {
        return '0003_create_stages_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_crm_stages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                pipeline_uid CHAR(26) NOT NULL,
                name_key VARCHAR(100) NOT NULL,
                display_name_json JSON NOT NULL,
                probability TINYINT UNSIGNED NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                state_type VARCHAR(20) NOT NULL DEFAULT 'open',
                color VARCHAR(20) NULL,
                rules_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_pipeline_uid (pipeline_uid, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_crm_stages');
    }
}
