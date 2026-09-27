<?php

declare(strict_types=1);

namespace Kontor\Projects\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section, permission list, or workflow diagram for
 * Projects in kontor.md — full gap-fill, same situation Tasks/
 * Collaboration/Dashboard/Reports/Purchasing/Expenses were in.
 * `customer_type`/`customer_uid` are polymorphic, matching kontor/sales'
 * own convention, not a hard dependency on kontor/contacts.
 */
final class Migration0001CreateProjectsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return '0001_create_projects_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_projects (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                customer_type VARCHAR(20) NULL,
                customer_uid CHAR(26) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                start_date DATE NULL,
                end_date DATE NULL,
                default_hourly_rate_minor BIGINT NULL,
                currency_code CHAR(3) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_code (organization_id, code),
                INDEX idx_organization_id (organization_id),
                INDEX idx_customer (customer_type, customer_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_projects');
    }
}
