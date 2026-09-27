<?php

declare(strict_types=1);

namespace Kontor\Entities\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "entity builder" milestone's actual data storage: every custom
 * entity type's records live in this one physical table, keyed by
 * definition_uid, with field values in `data_json`. A dynamically-created
 * real table per entity type (with its own migration) isn't feasible —
 * definitions are created at runtime, long after this package's own
 * migrations have already run — so this is a deliberate EAV-style
 * generic-row design, the same shape kontor_extensions (kontor.md#11.8)
 * already uses for arbitrary keyed metadata, just with real per-record
 * identity/lifecycle instead of a bag of key-value pairs.
 */
final class Migration0003CreateRecordsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'entities';
    }

    public function name(): string
    {
        return '0003_create_records_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_entity_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                data_json JSON NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_definition (definition_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_entity_records');
    }
}
