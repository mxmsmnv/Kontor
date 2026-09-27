<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Search\CatalogItemSearchProvider;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\SearchQuery;

final class CatalogItemSearchProviderTest extends DatabaseTestCase
{
    public function test_searches_localized_text_identifiers_and_excludes_inactive_items(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $items = new CatalogItemRepository($this->pdo, $organizations);
        $provider = new CatalogItemSearchProvider($this->pdo, $organizations);

        $widget = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Nebula Widget', 'fr' => 'Widget Nébuleuse'],
            sku: 'NEB-001',
            barcode: '900000000001',
            description: ['en' => 'A compact orbital tool'],
        );
        $service = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Installation'],
            itemType: 'service',
            sku: 'NEB-SERVICE',
            description: ['en' => 'Nebula setup at your office'],
        );
        $archived = CatalogItem::create($this->organizationUid, ['en' => 'Nebula Archived']);
        $discontinued = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Nebula Legacy'],
            status: 'discontinued',
        );

        foreach ([$widget, $service, $archived, $discontinued] as $item) {
            $items->save($item);
        }
        $items->archive($archived->uid->toString());

        $byText = $provider->search($this->query('Nebula'));
        $this->assertSame(2, $byText->total);
        $this->assertSame(
            [$widget->uid->toString(), $service->uid->toString()],
            array_column($byText->hits, 'entityUid')
        );
        $this->assertSame('Nebula Widget', $byText->hits[0]->title);
        $this->assertSame('NEB-001 · Product', $byText->hits[0]->subtitle);
        $this->assertSame(
            'catalog-item/?id=' . $widget->uid->toString(),
            $byText->hits[0]->url
        );

        $bySku = $provider->search($this->query('NEB-001'));
        $this->assertSame($widget->uid->toString(), $bySku->hits[0]->entityUid);
        $this->assertSame(5.0, $bySku->hits[0]->score);

        $byBarcode = $provider->search($this->query('900000000001'));
        $this->assertSame($widget->uid->toString(), $byBarcode->hits[0]->entityUid);

        $byFrenchTitle = $provider->search($this->query('Nébuleuse'));
        $this->assertSame($widget->uid->toString(), $byFrenchTitle->hits[0]->entityUid);
    }

    public function test_supports_catalog_items_and_honors_pagination(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $items = new CatalogItemRepository($this->pdo, $organizations);
        $provider = new CatalogItemSearchProvider($this->pdo, $organizations);

        $items->save(CatalogItem::create($this->organizationUid, ['en' => 'Orbit Alpha']));
        $items->save(CatalogItem::create($this->organizationUid, ['en' => 'Orbit Beta']));

        $result = $provider->search($this->query('Orbit', limit: 1, offset: 1));

        $this->assertTrue($provider->supports('catalog_item'));
        $this->assertFalse($provider->supports('contact'));
        $this->assertSame('catalog', $provider->name());
        $this->assertSame(2, $result->total);
        $this->assertCount(1, $result->hits);
    }

    private function query(string $term, int $limit = 20, int $offset = 0): SearchQuery
    {
        return new SearchQuery(
            organizationId: $this->organizationUid,
            term: $term,
            entityTypes: ['catalog_item'],
            limit: $limit,
            offset: $offset,
        );
    }
}
