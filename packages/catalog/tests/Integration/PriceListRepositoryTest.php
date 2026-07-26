<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class PriceListRepositoryTest extends DatabaseTestCase
{
    public function test_save_then_find_round_trips(): void
    {
        $repository = new PriceListRepository($this->pdo, new OrganizationRepository($this->pdo));
        $priceList = PriceList::create($this->organizationUid, 'Retail', 'EUR');

        $repository->save($priceList);
        $found = $repository->find($priceList->uid->toString());

        $this->assertSame('Retail', $found->name);
        $this->assertSame('EUR', $found->currencyCode);
    }

    public function test_for_organization_returns_all_its_price_lists(): void
    {
        $repository = new PriceListRepository($this->pdo, new OrganizationRepository($this->pdo));
        $repository->save(PriceList::create($this->organizationUid, 'Retail', 'EUR'));
        $repository->save(PriceList::create($this->organizationUid, 'Wholesale', 'EUR'));

        $this->assertCount(2, $repository->forOrganization($this->organizationUid));
    }

    public function test_find_all_filters_searches_and_paginates(): void
    {
        $repository = new PriceListRepository($this->pdo, new OrganizationRepository($this->pdo));
        $repository->save(PriceList::create($this->organizationUid, 'Retail', 'EUR'));
        $repository->save(PriceList::create($this->organizationUid, 'Seasonal Retail', 'EUR', 'inactive'));
        $repository->save(PriceList::create($this->organizationUid, 'Wholesale', 'EUR'));

        $this->assertSame(2, $repository->countMatching($this->organizationUid, 'Retail'));
        $this->assertSame(1, $repository->countMatching($this->organizationUid, '', 'inactive'));
        $this->assertSame('Retail', $repository->findAll($this->organizationUid, '', null, 1)[0]->name);
        $this->assertSame('Seasonal Retail', $repository->findAll($this->organizationUid, '', null, 1, 1)[0]->name);
    }

    public function test_require_returns_the_price_list_or_throws(): void
    {
        $repository = new PriceListRepository($this->pdo, new OrganizationRepository($this->pdo));
        $priceList = PriceList::create($this->organizationUid, 'Retail', 'EUR');
        $repository->save($priceList);

        $this->assertSame($priceList->uid->toString(), $repository->require($priceList->uid->toString())->uid->toString());

        $this->expectException(\RuntimeException::class);
        $repository->require('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }
}
