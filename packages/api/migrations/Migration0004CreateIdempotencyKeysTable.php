<?php

declare(strict_types=1);

namespace Kontor\API\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "idempotency" milestone (kontor.md#20.9): caches the full HTTP
 * response for a given `Idempotency-Key`, scoped per organization, so a
 * retried request returns the original result without re-invoking the
 * resource's `create()` at all. This is a distinct concern from
 * `kontor/inventory`'s own `idempotency_key` column on its movements
 * table — that one only prevents a duplicate *business record*; this one
 * caches the *API response envelope* itself, one layer up. No `uid`: a
 * row is addressed by its own `(organization_id, idempotency_key)` pair,
 * never referenced from anywhere else.
 */
final class Migration0004CreateIdempotencyKeysTable implements MigrationInterface
{
    public function component(): string
    {
        return 'api';
    }

    public function name(): string
    {
        return '0004_create_idempotency_keys_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_idempotency_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                idempotency_key VARCHAR(191) NOT NULL,
                request_fingerprint CHAR(64) NOT NULL,
                response_status SMALLINT UNSIGNED NOT NULL,
                response_json JSON NOT NULL,
                created_at DATETIME(6) NOT NULL,
                expires_at DATETIME(6) NULL,
                UNIQUE KEY uniq_key (organization_id, idempotency_key),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_idempotency_keys');
    }
}
