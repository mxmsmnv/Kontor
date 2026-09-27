<?php

declare(strict_types=1);

namespace Kontor\Automation\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "conditions" milestone: every condition on a rule must pass (AND
 * semantics) against the triggering event's data for its actions to run.
 * `field` is a dot-path into the event's data array (e.g.
 * 'data.movementType', mirroring kontor/documents' TemplateEngine's own
 * dot-path field resolution).
 */
final class Migration0002CreateConditionsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'automation';
    }

    public function name(): string
    {
        return '0002_create_conditions_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_automation_conditions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                rule_uid CHAR(26) NOT NULL,
                field VARCHAR(150) NOT NULL,
                operator VARCHAR(20) NOT NULL,
                value VARCHAR(255) NULL,
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
        $pdo->exec('DROP TABLE IF EXISTS kontor_automation_conditions');
    }
}
