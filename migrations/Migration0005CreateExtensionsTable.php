<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.8
 */
final class Migration0005CreateExtensionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0005_create_extensions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_extensions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                owner_component VARCHAR(191) NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                extension_key VARCHAR(191) NOT NULL,
                value_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_owner_entity_key (owner_component, entity_type, entity_uid, extension_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_extensions');
    }
}
