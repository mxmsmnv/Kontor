<?php

declare(strict_types=1);

namespace Kontor\Mail\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "shared mailboxes" milestone. No dedicated schema section in
 * kontor.md for Mail — full gap-fill, sections 11-16 stop before Stage 9.
 * Standard columns (kontor.md#10.3/10.4) apply in full — a mailbox is a
 * real, standalone entity multiple staff can be assigned messages from.
 */
final class Migration0001CreateMailboxesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'mail';
    }

    public function name(): string
    {
        return '0001_create_mailboxes_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_mail_mailboxes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                email_address VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_email (organization_id, email_address),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_mail_mailboxes');
    }
}
