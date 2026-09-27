<?php

declare(strict_types=1);

namespace Kontor\Automation\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#30: "Trigger -> Conditions -> Actions". No dedicated schema
 * section (11-16 stop at Inventory) or permission list — this migration
 * and its three siblings are this package's own design of that pipeline.
 * `trigger_event` is a Kontor event name (kontor.md#21's canonical event
 * envelope, e.g. 'inventory.movement.completed') — AutomationEngine
 * subscribes to Core's real EventDispatcherInterface for every distinct
 * trigger_event across active rules (see KontorAutomation::init()),
 * rather than inventing a second event system.
 */
final class Migration0001CreateRulesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'automation';
    }

    public function name(): string
    {
        return '0001_create_rules_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_automation_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                trigger_event VARCHAR(150) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_trigger (trigger_event, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_automation_rules');
    }
}
