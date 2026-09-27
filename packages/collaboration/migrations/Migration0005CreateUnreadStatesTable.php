<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "unread states" milestone's per-thread flavor: one row per
 * (user, entity) marking when that user last read that entity's comment
 * thread — the entity's unread count is comments created after
 * last_read_at (excluding the user's own), not a per-comment read flag,
 * which would need one row per comment per viewer and doesn't scale the
 * same way. UNIQUE(organization_id, user_id, entity_type, entity_uid)
 * makes markRead() an upsert.
 */
final class Migration0005CreateUnreadStatesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'collaboration';
    }

    public function name(): string
    {
        return '0005_create_unread_states_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_unread_states (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                last_read_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_unread_state (organization_id, user_id, entity_type, entity_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_unread_states');
    }
}
