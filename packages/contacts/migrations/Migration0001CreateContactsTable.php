<?php

declare(strict_types=1);

namespace Kontor\Contacts\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#12.1
 */
final class Migration0001CreateContactsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'contacts';
    }

    public function name(): string
    {
        return '0001_create_contacts_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_contacts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'individual',
                first_name VARCHAR(100) NULL,
                middle_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                display_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                mobile VARCHAR(50) NULL,
                job_title VARCHAR(150) NULL,
                preferred_language CHAR(5) NOT NULL DEFAULT 'en',
                preferred_currency CHAR(3) NULL,
                source VARCHAR(50) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                assigned_user_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_email (email),
                INDEX idx_phone (phone),
                INDEX idx_status (status),
                INDEX idx_assigned_user_id (assigned_user_id),
                FULLTEXT KEY ft_display_name_email (display_name, email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_contacts');
    }
}
