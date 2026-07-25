<?php

declare(strict_types=1);

namespace Kontor\Workflow\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#18: "A workflow definition contains: workflow key; entity
 * type; states; transitions; transition permissions; validators; required
 * fields; approval requirements; hooks; events; immutable states;
 * automatic actions." No dedicated schema section gives column names for
 * any of this (sections 11-16 stop at Inventory) — this migration and its
 * four siblings are this package's own design of that list. `states_json`
 * holds the full valid state list; a state with no outgoing rows in
 * kontor_workflow_transitions is "immutable" by construction — there's no
 * separate flag for it.
 */
final class Migration0001CreateDefinitionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'workflow';
    }

    public function name(): string
    {
        return '0001_create_definitions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_workflow_definitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                workflow_key VARCHAR(100) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                initial_state VARCHAR(50) NOT NULL,
                states_json JSON NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_key (organization_id, workflow_key),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity_type (entity_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_workflow_definitions');
    }
}
