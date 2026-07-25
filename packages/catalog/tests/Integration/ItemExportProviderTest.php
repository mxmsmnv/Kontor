<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Infrastructure\Export\ItemExportProvider;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ExportContext;

final class ItemExportProviderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $repository = new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo));
        $repository->save(CatalogItem::create($this->organizationUid, ['en' => 'Widget'], itemType: 'product'));
        $repository->save(CatalogItem::create($this->organizationUid, ['en' => 'Consulting'], itemType: 'service'));
    }

    private function provider(): ItemExportProvider
    {
        return new ItemExportProvider($this->pdo, new OrganizationRepository($this->pdo));
    }

    private function context(): ExportContext
    {
        return new ExportContext($this->organizationUid, 'user', 'usr_01');
    }

    public function test_count_and_iterate_all(): void
    {
        $provider = $this->provider();

        $this->assertSame(2, $provider->count([], $this->context()));
        $this->assertCount(2, iterator_to_array($provider->iterate([], [], $this->context())));
    }

    public function test_filters_by_item_type(): void
    {
        $rows = iterator_to_array($this->provider()->iterate(['item_type' => 'service'], [], $this->context()));

        $this->assertCount(1, $rows);
        $this->assertSame('service', $rows[0]['item_type']);
    }

    public function test_unknown_requested_fields_are_ignored_rather_than_causing_a_sql_error(): void
    {
        $rows = iterator_to_array($this->provider()->iterate([], ['uid', 'DROP TABLE kontor_catalog_items; --'], $this->context()));

        $this->assertSame(['uid'], array_keys($rows[0]));

        $stillExists = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_catalog_items')->fetchColumn();
        $this->assertSame(2, $stillExists);
    }
}
