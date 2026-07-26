<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Core\Domain\Organization;
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

    public function test_validity_filter_returns_current_upcoming_and_expired_lists(): void
    {
        $repository = new PriceListRepository($this->pdo, new OrganizationRepository($this->pdo));
        $today = new \DateTimeImmutable('today');
        $current = PriceList::create($this->organizationUid, 'Current', 'EUR');
        $upcoming = PriceList::create(
            $this->organizationUid,
            'Upcoming',
            'EUR',
            validFrom: $today->modify('+1 day'),
        );
        $expired = PriceList::create(
            $this->organizationUid,
            'Expired',
            'EUR',
            validTo: $today->modify('-1 day'),
        );

        foreach ([$current, $upcoming, $expired] as $priceList) {
            $repository->save($priceList);
        }

        foreach ([
            'current' => $current,
            'upcoming' => $upcoming,
            'expired' => $expired,
        ] as $validity => $expected) {
            $this->assertSame(1, $repository->countMatching(
                $this->organizationUid,
                validity: $validity,
            ));
            $this->assertSame(
                $expected->uid->toString(),
                $repository->findAll(
                    $this->organizationUid,
                    validity: $validity,
                )[0]->uid->toString(),
            );
        }
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

    public function test_bulk_status_changes_are_tenant_scoped_and_idempotent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $repository = new PriceListRepository($this->pdo, $organizations);
        $first = PriceList::create($this->organizationUid, 'Retail', 'EUR');
        $second = PriceList::create($this->organizationUid, 'Wholesale', 'EUR');
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = PriceList::create(
            $otherOrganization->uid->toString(),
            'Other tenant retail',
            'EUR',
        );

        foreach ([$first, $second, $other] as $priceList) {
            $repository->save($priceList);
        }

        $deactivated = $repository->deactivateMany($this->organizationUid, [
            $first->uid->toString(),
            $second->uid->toString(),
            $other->uid->toString(),
            $first->uid->toString(),
            'invalid',
        ]);

        $this->assertEqualsCanonicalizing(
            [$first->uid->toString(), $second->uid->toString()],
            $deactivated,
        );
        $this->assertSame([], $repository->deactivateMany($this->organizationUid, $deactivated));
        $this->assertSame(2, $repository->countMatching($this->organizationUid, status: 'inactive'));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString(), status: 'active'));

        $activated = $repository->activateMany($this->organizationUid, [
            $first->uid->toString(),
            $other->uid->toString(),
        ]);

        $this->assertSame([$first->uid->toString()], $activated);
        $this->assertSame(1, $repository->countMatching($this->organizationUid, status: 'inactive'));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString(), status: 'active'));
    }
}
