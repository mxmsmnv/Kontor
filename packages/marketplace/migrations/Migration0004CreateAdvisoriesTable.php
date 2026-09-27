<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "advisories" milestone. Synced from the same registry payload as
 * listings (see `RegistrySyncService`) rather than a separate feed — one
 * simplification this substage makes deliberately. `affected_versions`
 * is a single `Kontor\Core\Support\VersionConstraint` expression (e.g.
 * `"<0.1.1"`), the same small constraint grammar `kontor/core`'s own
 * `DependencyChecker` already uses for `requires`/`conflicts` — reused
 * directly rather than a second version-matching implementation.
 * Append-only once published (no `version`/`archived_at`), same
 * reasoning as `kontor/automation`'s execution log.
 */
final class Migration0004CreateAdvisoriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'marketplace';
    }

    public function name(): string
    {
        return '0004_create_advisories_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_marketplace_advisories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                package VARCHAR(191) NOT NULL,
                affected_versions VARCHAR(50) NOT NULL,
                severity VARCHAR(20) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                url VARCHAR(2048) NULL,
                published_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_package (package)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_marketplace_advisories');
    }
}
