<?php

declare(strict_types=1);

namespace Kontor\CRM\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#13.1
 */
final class Migration0001CreateLeadsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'crm';
    }

    public function name(): string
    {
        return '0001_create_leads_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_crm_leads (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                contact_uid CHAR(26) NULL,
                company_uid CHAR(26) NULL,
                source VARCHAR(50) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                priority VARCHAR(20) NOT NULL DEFAULT 'medium',
                estimated_value_minor BIGINT NULL,
                currency_code CHAR(3) NULL,
                assigned_user_id BIGINT UNSIGNED NULL,
                next_action_at DATETIME(6) NULL,
                converted_deal_uid CHAR(26) NULL,
                lost_reason VARCHAR(255) NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_status (status),
                INDEX idx_assigned_user_id (assigned_user_id),
                INDEX idx_contact_uid (contact_uid),
                INDEX idx_company_uid (company_uid),
                FULLTEXT KEY ft_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_crm_leads');
    }
}
