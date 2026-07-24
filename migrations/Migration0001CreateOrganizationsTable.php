<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.1
 */
final class Migration0001CreateOrganizationsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0001_create_organizations_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_organizations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                name VARCHAR(255) NOT NULL,
                legal_name VARCHAR(255) NULL,
                country_code CHAR(2) NOT NULL,
                default_language CHAR(5) NOT NULL DEFAULT 'en',
                default_currency CHAR(3) NOT NULL DEFAULT 'EUR',
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                settings_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_uid (uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_organizations');
    }
}
