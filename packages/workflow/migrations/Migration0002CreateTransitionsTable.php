<?php

declare(strict_types=1);

namespace Kontor\Workflow\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * One row per (from_state, action_key) — at most one destination, so
 * looking up "where does this action from this state go" is a single
 * unique lookup. `required_permission` is a permission name string a
 * caller is expected to have already checked (kontor.md#19.8's own list
 * puts permission checks in admin controllers/API endpoints, not this
 * engine) — WorkflowEngine::transition() just refuses to proceed if the
 * caller says it wasn't granted.
 */
final class Migration0002CreateTransitionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'workflow';
    }

    public function name(): string
    {
        return '0002_create_transitions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_workflow_transitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                action_key VARCHAR(100) NOT NULL,
                from_state VARCHAR(50) NOT NULL,
                to_state VARCHAR(50) NOT NULL,
                required_permission VARCHAR(100) NULL,
                requires_approval TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_from_action (definition_uid, from_state, action_key),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_workflow_transitions');
    }
}
