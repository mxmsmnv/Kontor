<?php

declare(strict_types=1);

namespace Kontor\Entities\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section for Custom Entities in kontor.md (sections
 * 11-16 stop at Inventory) — full gap-fill. `view_permission`/
 * `edit_permission` are permission-name strings a caller is expected to
 * have already checked (same pattern as kontor/workflow's transitions'
 * `required_permission` — kontor.md#19's own list puts permission checks
 * in admin controllers/API endpoints, not a service layer). `api_exposed`
 * is the "API exposure" milestone's own flag — see EntitySchemaService.
 */
final class Migration0001CreateDefinitionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'entities';
    }

    public function name(): string
    {
        return '0001_create_definitions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_entity_definitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                entity_key VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                view_permission VARCHAR(100) NULL,
                edit_permission VARCHAR(100) NULL,
                api_exposed TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_key (organization_id, entity_key),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_entity_definitions');
    }
}
