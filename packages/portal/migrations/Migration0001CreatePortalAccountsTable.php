<?php

declare(strict_types=1);

namespace Kontor\Portal\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "customer login" milestone. No dedicated schema section in
 * kontor.md for Portal — full gap-fill. A portal account is always
 * linked to exactly one `kontor/contacts` Contact (`contact_uid`) — a
 * portal account shared across every contact at one company is out of
 * scope for this substage (see README "Not in scope"). Standard columns
 * apply in full; only `password_hash` is ever stored (PHP's own
 * `password_hash()`), never the plaintext, the same one-way-hash
 * principle `kontor/api`'s own API tokens already use.
 */
final class Migration0001CreatePortalAccountsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'portal';
    }

    public function name(): string
    {
        return '0001_create_portal_accounts_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_portal_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                contact_uid CHAR(26) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                last_login_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_org_email (organization_id, email),
                INDEX idx_organization_id (organization_id),
                INDEX idx_contact_uid (contact_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_portal_accounts');
    }
}
