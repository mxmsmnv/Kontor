<?php

declare(strict_types=1);

namespace Kontor\CRM\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#13.2
 */
final class Migration0002CreatePipelinesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'crm';
    }

    public function name(): string
    {
        return '0002_create_pipelines_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_crm_pipelines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                entity_type VARCHAR(30) NOT NULL DEFAULT 'deal',
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                settings_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_crm_pipelines');
    }
}
