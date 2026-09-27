<?php

declare(strict_types=1);

namespace Kontor\AI\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

final class Migration0002CreateExternalApprovalsTable implements MigrationInterface
{
    public function component(): string { return 'ai'; }
    public function name(): string { return '0002_create_external_approvals_table'; }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_ai_external_approvals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(32) NOT NULL,
                external_id VARCHAR(64) NOT NULL,
                pending_uid CHAR(26) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_provider_external (provider, external_id),
                UNIQUE KEY uniq_pending_uid (pending_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_ai_external_approvals');
    }
}
