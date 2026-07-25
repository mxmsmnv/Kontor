<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "layouts" milestone: one row per widget placed on a dashboard.
 * Lighter junction-style table, like kontor_payment_allocations — no
 * version/archived_at; removing a widget from a layout deletes the row
 * outright rather than archiving it (there's nothing worth retaining about
 * where a widget briefly sat before someone moved it).
 */
final class Migration0002CreateDashboardWidgetsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'dashboard';
    }

    public function name(): string
    {
        return '0002_create_dashboard_widgets_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_dashboard_widgets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                dashboard_uid CHAR(26) NOT NULL,
                widget_key VARCHAR(100) NOT NULL,
                position_x INT NOT NULL DEFAULT 0,
                position_y INT NOT NULL DEFAULT 0,
                width INT NOT NULL DEFAULT 4,
                height INT NOT NULL DEFAULT 3,
                config_json JSON NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_dashboard (dashboard_uid, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_dashboard_widgets');
    }
}
