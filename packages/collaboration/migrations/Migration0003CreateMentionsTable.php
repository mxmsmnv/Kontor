<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * A lighter junction-style table, like kontor_payment_allocations: no
 * version/archived_at. `read_at` is this table's own "unread state" — a
 * per-mention flavor of the "unread states" milestone distinct from
 * kontor_unread_states' per-thread flavor (see that migration's comment).
 */
final class Migration0003CreateMentionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'collaboration';
    }

    public function name(): string
    {
        return '0003_create_mentions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_mentions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                comment_uid CHAR(26) NOT NULL,
                mentioned_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL,
                read_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_comment (comment_uid),
                INDEX idx_unread_for_user (organization_id, mentioned_user_id, read_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_mentions');
    }
}
