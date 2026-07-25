<?php

declare(strict_types=1);

namespace Kontor\API\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "authentication" milestone (kontor.md#20.1's "scoped API token").
 * No dedicated schema section in kontor.md for the API component — full
 * gap-fill, sections 11-16 stop before Stage 8. Only `token_hash` is ever
 * stored (SHA-256 of the token value) — the raw token is shown to the
 * caller exactly once, at creation, the same principle
 * `kontor/files`' signed URLs already apply to secrets that must never be
 * recoverable from the database.
 */
final class Migration0001CreateApiTokensTable implements MigrationInterface
{
    public function component(): string
    {
        return 'api';
    }

    public function name(): string
    {
        return '0001_create_api_tokens_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_api_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                scopes_json JSON NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                last_used_at DATETIME(6) NULL,
                expires_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_token_hash (token_hash),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_api_tokens');
    }
}
