<?php

declare(strict_types=1);

namespace Kontor\Entities\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "fields" milestone. `field_type` matches the same small scalar-type
 * vocabulary kontor/reports' own ReportSchema uses for provider fields
 * (string|int|decimal|date|money), extended with bool/datetime for a
 * general-purpose entity builder.
 */
final class Migration0002CreateFieldsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'entities';
    }

    public function name(): string
    {
        return '0002_create_fields_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_entity_fields (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                definition_uid CHAR(26) NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                label VARCHAR(255) NOT NULL,
                field_type VARCHAR(20) NOT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_field_key (definition_uid, field_key),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_entity_fields');
    }
}
