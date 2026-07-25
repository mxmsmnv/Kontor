<?php

declare(strict_types=1);

namespace Kontor\Catalog\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * Not in kontor.md#14 explicitly — section 14 defines items, price lists
 * and price list items only, but kontor_catalog_items.category_uid
 * presupposes a category entity with its own uid. Filled the same way
 * kontor_extensions filled the "tags" gap for Contacts: standard columns
 * (kontor.md#10.3/10.4), hierarchical via a self-referencing parent_uid.
 */
final class Migration0002CreateCategoriesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'catalog';
    }

    public function name(): string
    {
        return '0002_create_categories_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_catalog_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                parent_uid CHAR(26) NULL,
                name_json JSON NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_parent_uid (parent_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_catalog_categories');
    }
}
