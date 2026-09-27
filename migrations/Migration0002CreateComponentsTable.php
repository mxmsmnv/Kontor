<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.2
 */
final class Migration0002CreateComponentsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0002_create_components_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_components (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                version VARCHAR(32) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'disabled',
                source VARCHAR(255) NULL,
                checksum CHAR(64) NULL,
                installed_at DATETIME(6) NULL,
                updated_at DATETIME(6) NULL,
                enabled_at DATETIME(6) NULL,
                disabled_at DATETIME(6) NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_components');
    }
}
