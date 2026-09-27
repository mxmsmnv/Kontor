<?php

declare(strict_types=1);

namespace Kontor\Automation\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "actions" milestone. `action_key` names a registered
 * ActionHandlerInterface implementation (this package's own extension
 * point, not an SDK contract — see WorkflowEngine's WidgetProviderInterface
 * precedent); `params_json` is passed to the handler alongside the
 * triggering event's data.
 */
final class Migration0003CreateActionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'automation';
    }

    public function name(): string
    {
        return '0003_create_actions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_automation_actions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                rule_uid CHAR(26) NOT NULL,
                action_key VARCHAR(100) NOT NULL,
                params_json JSON NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_rule (rule_uid, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_automation_actions');
    }
}
