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
}
