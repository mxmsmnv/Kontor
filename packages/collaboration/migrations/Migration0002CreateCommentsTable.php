<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * `parent_uid` threads replies under a top-level comment; a reply shares
 * its parent's entity_type/entity_uid rather than pointing at the parent
 * as its own "entity" — the whole thread belongs to one entity.
 */
final class Migration0002CreateCommentsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'collaboration';
    }

    public function name(): string
    {
        return '0002_create_comments_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_comments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                parent_uid CHAR(26) NULL,
                body TEXT NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity (entity_type, entity_uid, created_at),
                INDEX idx_parent (parent_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_comments');
    }
}
