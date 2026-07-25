<?php

declare(strict_types=1);

namespace Kontor\Catalog\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#14.2
 */
final class Migration0003CreatePriceListsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'catalog';
    }

    public function name(): string
    {
        return '0003_create_price_lists_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_catalog_price_lists (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                currency_code CHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                valid_from DATE NULL,
                valid_to DATE NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_catalog_price_lists');
    }
}
