<?php

declare(strict_types=1);

namespace Kontor\API\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "webhooks" milestone (kontor.md#20.11). `event_pattern` is an exact
 * event name, not a glob — `Kontor\Core\Infrastructure\Events\EventDispatcher`
 * (kontor.md#9.3) only supports subscribing to one exact event name at a
 * time, the same constraint `kontor/automation`'s `AutomationEngine`
 * already works within. `secret` signs each delivery (kontor.md#20.11
 * "signature") via HMAC-SHA256 — see `WebhookDeliveryService`.
 */
final class Migration0002CreateWebhookSubscriptionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'api';
    }

    public function name(): string
    {
        return '0002_create_webhook_subscriptions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_webhook_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                url VARCHAR(2048) NOT NULL,
                event_pattern VARCHAR(150) NOT NULL,
                secret VARCHAR(191) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_event_pattern (event_pattern, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_webhook_subscriptions');
    }
}
