<?php

declare(strict_types=1);

namespace Kontor\Contacts\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#12.4 — this table is the one place the spec's own schema has
 * no `uid`/`created_at` columns; followed exactly as given rather than
 * "improved", per the instruction not to invent alternative architecture.
 */
final class Migration0004CreateContactCompanyTable implements MigrationInterface
{
    public function component(): string
    {
        return 'contacts';
    }

    public function name(): string
    {
        return '0004_create_contact_company_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_contact_company (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                contact_uid CHAR(26) NOT NULL,
                company_uid CHAR(26) NOT NULL,
                role VARCHAR(100) NULL,
                department VARCHAR(100) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                started_at DATE NULL,
                ended_at DATE NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_membership (contact_uid, company_uid, role),
                INDEX idx_organization_id (organization_id),
                INDEX idx_contact_uid (contact_uid),
                INDEX idx_company_uid (company_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_contact_company');
    }
}
