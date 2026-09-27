<?php

declare(strict_types=1);

namespace Kontor\Workflow\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "state machine" milestone's live state: one row per entity tracking
 * which state it's currently in under a given definition.
 * UNIQUE(organization_id, entity_type, entity_uid) — an entity can only be
 * under one active workflow instance at a time.
 */
final class Migration0003CreateInstancesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'workflow';
    }

    public function name(): string
    {
        return '0003_create_instances_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_workflow_instances (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                current_state VARCHAR(50) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_entity (organization_id, entity_type, entity_uid),
                INDEX idx_definition (definition_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_workflow_instances');
    }
}
