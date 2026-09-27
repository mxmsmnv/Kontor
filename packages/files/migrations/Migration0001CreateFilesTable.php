<?php

declare(strict_types=1);

namespace Kontor\Files\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.6
 */
final class Migration0001CreateFilesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'files';
    }

    public function name(): string
    {
        return '0001_create_files_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_files (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                storage VARCHAR(50) NOT NULL DEFAULT 'local',
                path VARCHAR(500) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(127) NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                checksum CHAR(64) NOT NULL,
                visibility VARCHAR(20) NOT NULL DEFAULT 'private',
                classification VARCHAR(50) NULL,
                entity_type VARCHAR(191) NULL,
                entity_uid CHAR(26) NULL,
                version_number INT UNSIGNED NOT NULL DEFAULT 1,
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity (entity_type, entity_uid),
                INDEX idx_version_family (entity_type, entity_uid, original_name, version_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_files');
    }
}
