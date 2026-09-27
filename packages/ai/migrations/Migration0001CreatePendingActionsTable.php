<?php

declare(strict_types=1);

namespace Kontor\AI\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "approval workflow" milestone (kontor.md#32: "Critical AI actions
 * require confirmation unless an explicit approved automation policy
 * allows them"). No dedicated schema section in kontor.md for AI — full
 * gap-fill. Mutated once (`pending` → `approved`/`rejected`), not
 * append-only, so no `version`/`archived_at` — same reasoning
 * `kontor/api`'s webhook deliveries and `kontor/marketplace`'s
 * advisories already used for their own non-standard tables.
 */
final class Migration0001CreatePendingActionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'ai';
    }

    public function name(): string
    {
        return '0001_create_pending_actions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_ai_pending_actions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                capability VARCHAR(100) NOT NULL,
                input_json JSON NOT NULL,
                output_json JSON NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                requested_by BIGINT UNSIGNED NULL,
                decided_by BIGINT UNSIGNED NULL,
                created_at DATETIME(6) NOT NULL,
                decided_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_ai_pending_actions');
    }
}
