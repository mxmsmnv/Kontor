<?php

declare(strict_types=1);

namespace Kontor\Entities\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "views" milestone: a saved filter/sort/column configuration over a
 * custom entity type's records.
 */
final class Migration0004CreateViewsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'entities';
    }

    public function name(): string
    {
        return '0004_create_views_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_entity_views (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                name VARCHAR(255) NOT NULL,
                filters_json JSON NULL,
                sort_json JSON NULL,
                columns_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_definition (definition_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_entity_views');
    }
}
