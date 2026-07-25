<?php

declare(strict_types=1);

namespace Kontor\Workflow\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "history" milestone: an append-only ledger of every completed
 * transition, same shape as kontor/inventory's movement ledger — never
 * edited after the fact, only inserted and read.
 */
final class Migration0005CreateHistoryTable implements MigrationInterface
{
    public function component(): string
    {
        return 'workflow';
    }

    public function name(): string
    {
        return '0005_create_history_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_workflow_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                action_key VARCHAR(100) NOT NULL,
                from_state VARCHAR(50) NOT NULL,
                to_state VARCHAR(50) NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                occurred_at DATETIME(6) NOT NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity (entity_type, entity_uid, occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_workflow_history');
    }
}
