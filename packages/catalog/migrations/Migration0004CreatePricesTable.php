<?php

declare(strict_types=1);

namespace Kontor\Catalog\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#14.3 — like kontor_contact_company, this table has no uid of
 * its own in the spec's schema; followed exactly.
 */
final class Migration0004CreatePricesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'catalog';
    }

    public function name(): string
    {
        return '0004_create_prices_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_catalog_prices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                price_list_uid CHAR(26) NOT NULL,
                item_uid CHAR(26) NOT NULL,
                price_minor BIGINT NOT NULL,
                currency_code CHAR(3) NOT NULL,
                min_quantity DECIMAL(20,6) NOT NULL DEFAULT 1,
                valid_from DATE NULL,
                valid_to DATE NULL,
                INDEX idx_price_list_uid (price_list_uid),
                INDEX idx_item_uid (item_uid),
                UNIQUE KEY uniq_tier (price_list_uid, item_uid, min_quantity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_catalog_prices');
    }
}
