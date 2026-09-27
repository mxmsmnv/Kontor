<?php

declare(strict_types=1);

namespace Kontor\API\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "webhooks" milestone's delivery log (kontor.md#20.11 "delivery
 * log", "retries", "replay"). Unlike an append-only log such as
 * `kontor/automation`'s execution log, a delivery row is mutated across
 * retry attempts (`attempt_count`/`status`/`next_attempt_at`) until it
 * reaches a terminal state — so no `version`/`archived_at`, same
 * reasoning `kontor/inventory`'s movement ledger already used for why it
 * skips columns that don't apply to a given table's own lifecycle.
 */
final class Migration0003CreateWebhookDeliveriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'api';
    }

    public function name(): string
    {
        return '0003_create_webhook_deliveries_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_webhook_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                subscription_uid CHAR(26) NOT NULL,
                event_uid CHAR(26) NOT NULL,
                event_name VARCHAR(150) NOT NULL,
                payload_json JSON NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                response_code INT UNSIGNED NULL,
                last_error VARCHAR(500) NULL,
                next_attempt_at DATETIME(6) NULL,
                delivered_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_subscription (subscription_uid, status),
                INDEX idx_next_attempt (status, next_attempt_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_webhook_deliveries');
    }
}
