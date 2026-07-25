<?php

declare(strict_types=1);

namespace Kontor\Contacts\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#12.2
 */
final class Migration0002CreateCompaniesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'contacts';
    }

    public function name(): string
    {
        return '0002_create_companies_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_companies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                legal_name VARCHAR(255) NOT NULL,
                trading_name VARCHAR(255) NULL,
                registration_number VARCHAR(50) NULL,
                tax_number VARCHAR(50) NULL,
                vat_number VARCHAR(50) NULL,
                website VARCHAR(255) NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                preferred_language CHAR(5) NOT NULL DEFAULT 'en',
                preferred_currency CHAR(3) NULL,
                payment_terms_days SMALLINT UNSIGNED NULL,
                credit_limit_minor BIGINT NULL,
                credit_limit_currency CHAR(3) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                assigned_user_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_status (status),
                INDEX idx_assigned_user_id (assigned_user_id),
                FULLTEXT KEY ft_names_email (legal_name, trading_name, email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_companies');
    }
}
