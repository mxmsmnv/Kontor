<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "followers" milestone. UNIQUE(organization_id, entity_type,
 * entity_uid, user_id) makes following idempotent at the database level,
 * not just in application code.
 */
final class Migration0004CreateFollowersTable implements MigrationInterface
{
    public function component(): string
    {
        return 'collaboration';
    }

    public function name(): string
    {
        return '0004_create_followers_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_followers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_follow (organization_id, entity_type, entity_uid, user_id),
                INDEX idx_entity (entity_type, entity_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_followers');
    }
}
