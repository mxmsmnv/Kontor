<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.7
 */
final class Migration0006CreateRelationsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0006_create_relations_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_relations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                source_type VARCHAR(191) NOT NULL,
                source_uid CHAR(26) NOT NULL,
                target_type VARCHAR(191) NOT NULL,
                target_uid CHAR(26) NOT NULL,
                relation_type VARCHAR(64) NOT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT 'directed',
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_source (source_type, source_uid),
                INDEX idx_target (target_type, target_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_relations');
    }
}
