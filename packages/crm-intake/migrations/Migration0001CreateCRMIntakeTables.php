<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

final class Migration0001CreateCRMIntakeTables implements MigrationInterface
{
    public function component(): string
    {
        return 'crm_intake';
    }

    public function name(): string
    {
        return '0001_create_crm_intake_tables';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_crm_intake_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                fields_json JSON NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_organization_name (organization_id, name),
                INDEX idx_organization_default (organization_id, is_default, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_crm_intake_responses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                profile_uid CHAR(26) NOT NULL,
                entity_type VARCHAR(20) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                answers_json JSON NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_entity_response (organization_id, entity_type, entity_uid),
                INDEX idx_profile_uid (profile_uid),
                INDEX idx_entity (entity_type, entity_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_responses');
        $pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_profiles');
    }
}
