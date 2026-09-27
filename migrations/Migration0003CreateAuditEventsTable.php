<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.4
 */
final class Migration0003CreateAuditEventsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0003_create_audit_events_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                component VARCHAR(191) NOT NULL,
                entity_type VARCHAR(191) NOT NULL,
                entity_uid CHAR(26) NOT NULL,
                action VARCHAR(64) NOT NULL,
                actor_type VARCHAR(32) NOT NULL,
                actor_uid CHAR(26) NULL,
                occurred_at DATETIME(6) NOT NULL,
                request_id CHAR(26) NULL,
                correlation_id CHAR(26) NULL,
                ip_address VARCHAR(45) NULL,
                previous_json JSON NULL,
                current_json JSON NULL,
                metadata_json JSON NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_entity (entity_type, entity_uid),
                INDEX idx_occurred_at (occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_audit_events');
    }
}
