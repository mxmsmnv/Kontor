<?php

declare(strict_types=1);

namespace Kontor\Catalog\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class CatalogHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'catalog';
    }

    public function run(): HealthCheckResult
    {
        try {
            $items = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_catalog_items WHERE archived_at IS NULL')->fetchColumn();
            $categories = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_catalog_categories WHERE archived_at IS NULL')->fetchColumn();
            $priceLists = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_catalog_price_lists')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$items} item(s), {$categories} categor(y/ies), {$priceLists} price list(s).",
                ['items' => $items, 'categories' => $categories, 'priceLists' => $priceLists],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Catalog tables are not reachable: {$e->getMessage()}");
        }
    }
}
