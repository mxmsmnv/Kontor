<?php

declare(strict_types=1);

namespace Kontor\Mail\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "outbound history"/"inbound adapters" milestones' storage: one row
 * per message, either sent by Kontor or received into a mailbox.
 * `mailbox_uid` is nullable — an outbound message doesn't have to be sent
 * from a shared mailbox. "Entity linking" (the other milestone) doesn't
 * get its own column here at all — it reuses `kontor/core`'s own
 * `kontor_relations` table (kontor.md#11.7) directly, the same choice
 * `kontor/tasks` and `kontor/entities` already made for their own
 * "relations" milestones.
 */
final class Migration0002CreateMessagesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'mail';
    }

    public function name(): string
    {
        return '0002_create_messages_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_mail_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                mailbox_uid CHAR(26) NULL,
                direction VARCHAR(10) NOT NULL,
                from_address VARCHAR(255) NOT NULL,
                to_addresses_json JSON NOT NULL,
                cc_addresses_json JSON NULL,
                subject VARCHAR(500) NOT NULL,
                body_text MEDIUMTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'queued',
                error VARCHAR(500) NULL,
                assigned_to BIGINT UNSIGNED NULL,
                occurred_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_mailbox (mailbox_uid, direction),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_mail_messages');
    }
}
