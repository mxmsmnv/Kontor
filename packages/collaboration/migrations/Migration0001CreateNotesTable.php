<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section for Collaboration in kontor.md (sections
 * 11-16 stop at Inventory) — same gap-fill situation as Tasks before it.
 * A note is polymorphic (entity_type/entity_uid), attachable to anything,
 * same pattern as kontor_files.
 */
final class Migration0001CreateNotesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'collaboration';
    }

    public function name(): string
    {
        return '0001_create_notes_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_notes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity (entity_type, entity_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_notes');
    }
}
