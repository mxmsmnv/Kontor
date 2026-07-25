<?php

declare(strict_types=1);

namespace Kontor\Contacts\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#12.3
 */
final class Migration0003CreateAddressesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'contacts';
    }

    public function name(): string
    {
        return '0003_create_addresses_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                owner_type VARCHAR(50) NOT NULL,
                owner_uid CHAR(26) NOT NULL,
                address_type VARCHAR(30) NOT NULL DEFAULT 'billing',
                recipient_name VARCHAR(255) NULL,
                company_name VARCHAR(255) NULL,
                line1 VARCHAR(255) NOT NULL,
                line2 VARCHAR(255) NULL,
                city VARCHAR(150) NOT NULL,
                region VARCHAR(150) NULL,
                postal_code VARCHAR(20) NULL,
                country_code CHAR(2) NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                metadata_json JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_owner (owner_type, owner_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_addresses');
    }
}
