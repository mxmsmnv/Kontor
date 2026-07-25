<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * No dedicated schema section for Dashboard in kontor.md (sections 11-16
 * stop at Inventory) — same gap-fill situation as Tasks/Collaboration
 * before it. Substage 5.3's milestones are "personal dashboards" and
 * "role dashboards" only — organization dashboards (kontor.md#29's fuller
 * feature list) are deferred, so `scope` only supports 'personal'|'role'.
 * "Only one default per scope" is an application-level invariant
 * (DashboardService), not a DB constraint — same choice SequenceService
 * made for its own single-writer invariants.
 */
final class Migration0001CreateDashboardsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'dashboard';
    }

    public function name(): string
    {
        return '0001_create_dashboards_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_dashboards (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                scope VARCHAR(10) NOT NULL,
                owner_user_id BIGINT UNSIGNED NULL,
                role VARCHAR(100) NULL,
                name VARCHAR(255) NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_personal (organization_id, owner_user_id, is_default),
                INDEX idx_role (organization_id, role, is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_dashboards');
    }
}
