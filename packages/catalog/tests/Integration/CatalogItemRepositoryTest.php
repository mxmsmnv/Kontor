<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

final class CatalogItemRepositoryTest extends DatabaseTestCase
{
    private function repository(): CatalogItemRepository
    {
        return new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips_including_money(): void
    {
        $repository = $this->repository();
        $item = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Widget', 'fr' => 'Gadget'],
            sku: 'WID-001',
            salesPrice: Money::ofMinor(1999, 'EUR'),
        );

        $repository->save($item);
        $found = $repository->find($item->uid->toString());

        $this->assertSame(['en' => 'Widget', 'fr' => 'Gadget'], $found->title);
        $this->assertSame('WID-001', $found->sku);
        $this->assertSame(1999, $found->salesPrice->amountMinor());
        $this->assertSame('EUR', $found->salesPrice->currencyCode());
    }

    public function test_find_by_sku(): void
    {
        $repository = $this->repository();
        $item = CatalogItem::create($this->organizationUid, ['en' => 'Widget'], sku: 'WID-001');
        $repository->save($item);

        $this->assertSame($item->uid->toString(), $repository->findBySku($this->organizationUid, 'WID-001')->uid->toString());
        $this->assertNull($repository->findBySku($this->organizationUid, 'NOPE'));
    }

    public function test_save_upserts_rather_than_duplicating(): void
    {
        $repository = $this->repository();
        $item = CatalogItem::create($this->organizationUid, ['en' => 'Widget']);
        $repository->save($item);

        $item->status = 'discontinued';
        $repository->save($item);

        $this->assertSame('discontinued', $repository->find($item->uid->toString())->status);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_catalog_items')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $item = CatalogItem::create($this->organizationUid, ['en' => 'Widget']);
        $repository->save($item);

        $repository->archive($item->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_catalog_items')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $repository->restore($item->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_catalog_items')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }

    public function test_bulk_archive_and_restore_are_tenant_scoped_and_idempotent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $repository = new CatalogItemRepository($this->pdo, $organizations);
        $first = CatalogItem::create($this->organizationUid, ['en' => 'First']);
        $second = CatalogItem::create($this->organizationUid, ['en' => 'Second']);
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = CatalogItem::create(
            $otherOrganization->uid->toString(),
            ['en' => 'Other tenant item']
        );

        foreach ([$first, $second, $other] as $item) {
            $repository->save($item);
        }

        $archived = $repository->archiveMany($this->organizationUid, [
            $first->uid->toString(),
            $second->uid->toString(),
            $other->uid->toString(),
            $first->uid->toString(),
            'invalid',
        ]);

        $this->assertEqualsCanonicalizing(
            [$first->uid->toString(), $second->uid->toString()],
            $archived
        );
        $this->assertSame([], $repository->archiveMany($this->organizationUid, $archived));
        $this->assertSame(2, $repository->countMatching($this->organizationUid, archived: true));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString()));

        $restored = $repository->restoreMany($this->organizationUid, [
            $first->uid->toString(),
            $other->uid->toString(),
        ]);

        $this->assertSame([$first->uid->toString()], $restored);
        $this->assertSame(1, $repository->countMatching($this->organizationUid, archived: true));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString()));
    }

    public function test_bulk_status_changes_are_tenant_scoped_and_idempotent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $repository = new CatalogItemRepository($this->pdo, $organizations);
        $first = CatalogItem::create($this->organizationUid, ['en' => 'First']);
        $second = CatalogItem::create($this->organizationUid, ['en' => 'Second']);
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = CatalogItem::create(
            $otherOrganization->uid->toString(),
            ['en' => 'Other item'],
        );

        foreach ([$first, $second, $other] as $item) {
            $repository->save($item);
        }

        $discontinued = $repository->discontinueMany($this->organizationUid, [
            $first->uid->toString(),
            $second->uid->toString(),
            $other->uid->toString(),
            $first->uid->toString(),
            'invalid',
        ]);

        $this->assertEqualsCanonicalizing(
            [$first->uid->toString(), $second->uid->toString()],
            $discontinued,
        );
        $this->assertSame([], $repository->discontinueMany($this->organizationUid, $discontinued));
        $this->assertSame(2, $repository->countMatching($this->organizationUid, status: 'discontinued'));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString(), status: 'active'));

        $deactivated = $repository->deactivateMany($this->organizationUid, [
            $first->uid->toString(),
            $other->uid->toString(),
        ]);
        $this->assertSame([$first->uid->toString()], $deactivated);

        $activated = $repository->activateMany($this->organizationUid, [
            $first->uid->toString(),
            $other->uid->toString(),
        ]);
        $this->assertSame([$first->uid->toString()], $activated);
        $this->assertSame(1, $repository->countMatching($this->organizationUid, status: 'active'));
        $this->assertSame(1, $repository->countMatching($otherOrganization->uid->toString(), status: 'active'));
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_rejects_a_non_catalog_item_entity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository()->save(new \stdClass());
    }

    public function test_service_item_type_round_trips(): void
    {
        $repository = $this->repository();
        $item = CatalogItem::create($this->organizationUid, ['en' => 'Consulting'], itemType: 'service');
        $repository->save($item);

        $this->assertTrue($repository->find($item->uid->toString())->isService());
    }

    public function test_list_search_type_archive_counts_and_pagination(): void
    {
        $repository = $this->repository();
        $widget = CatalogItem::create($this->organizationUid, ['en' => 'Nebula Widget'], sku: 'NEB-001');
        $service = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Nebula Consulting'],
            itemType: 'service',
            sku: 'NEB-002',
        );
        $repository->save($widget);
        $repository->save($service);

        $this->assertSame(2, $repository->countMatching($this->organizationUid, 'Nebula'));
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Nebula', 'service'));
        $first = $repository->findAll($this->organizationUid, 'Nebula', limit: 1);
        $second = $repository->findAll($this->organizationUid, 'Nebula', limit: 1, offset: 1);
        $this->assertNotSame($first[0]->uid->toString(), $second[0]->uid->toString());

        $repository->archive($widget->uid->toString());

        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Nebula'));
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Nebula', archived: true));
    }

    public function test_reference_usage_counts_only_active_items(): void
    {
        $repository = $this->repository();
        $piece = CatalogItem::create($this->organizationUid, ['en' => 'Piece']);
        $piece->unitCode = 'pcs';
        $piece->taxCode = 'standard';
        $box = CatalogItem::create($this->organizationUid, ['en' => 'Box']);
        $box->unitCode = 'box';
        $box->taxCode = 'reduced';
        $repository->save($piece);
        $repository->save($box);
        $repository->archive($box->uid->toString());

        $this->assertSame([
            'units' => ['pcs' => 1],
            'taxes' => ['standard' => 1],
        ], $repository->referenceUsage($this->organizationUid));
    }

    public function test_status_filter_returns_only_matching_items(): void
    {
        $repository = $this->repository();
        $active = CatalogItem::create($this->organizationUid, ['en' => 'Active item']);
        $inactive = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Inactive item'],
            status: 'inactive',
        );
        $discontinued = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Discontinued item'],
            status: 'discontinued',
        );

        foreach ([$active, $inactive, $discontinued] as $item) {
            $repository->save($item);
        }

        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            status: 'inactive',
        ));
        $this->assertSame(
            $inactive->uid->toString(),
            $repository->findAll(
                $this->organizationUid,
                status: 'inactive',
            )[0]->uid->toString(),
        );

        $tracked = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Tracked item'],
            trackInventory: true,
        );
        $repository->save($tracked);
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            trackInventory: true,
        ));
        $this->assertSame(
            $tracked->uid->toString(),
            $repository->findAll(
                $this->organizationUid,
                trackInventory: true,
            )[0]->uid->toString(),
        );
    }

    public function test_category_filter_and_usage_counts_only_active_items(): void
    {
        $repository = $this->repository();
        $hardwareUid = Uid::generate()->toString();
        $servicesUid = Uid::generate()->toString();
        $laptop = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Laptop'],
            categoryUid: $hardwareUid,
        );
        $monitor = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Monitor'],
            categoryUid: $hardwareUid,
        );
        $consulting = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Consulting'],
            itemType: 'service',
            categoryUid: $servicesUid,
        );
        $uncategorized = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Loose item'],
        );

        foreach ([$laptop, $monitor, $consulting, $uncategorized] as $item) {
            $repository->save($item);
        }
        $repository->archive($monitor->uid->toString());

        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            categoryUid: $hardwareUid,
        ));
        $this->assertSame(
            $consulting->uid->toString(),
            $repository->findAll(
                $this->organizationUid,
                categoryUid: $servicesUid,
            )[0]->uid->toString(),
        );
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            categoryUid: 'uncategorized',
        ));
        $this->assertSame(
            $uncategorized->uid->toString(),
            $repository->findAll(
                $this->organizationUid,
                categoryUid: 'uncategorized',
            )[0]->uid->toString(),
        );
        $this->assertSame([
            $hardwareUid => 1,
            $servicesUid => 1,
        ], $repository->categoryUsage($this->organizationUid));
    }

    public function test_summary_counts_catalog_shapes_for_one_organization(): void
    {
        $repository = $this->repository();
        $tracked = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Tracked product'],
            trackInventory: true,
        );
        $service = CatalogItem::create(
            $this->organizationUid,
            ['en' => 'Service'],
            itemType: 'service',
        );
        $archived = CatalogItem::create($this->organizationUid, ['en' => 'Archived product']);

        foreach ([$tracked, $service, $archived] as $item) {
            $repository->save($item);
        }
        $repository->archive($archived->uid->toString());

        $this->assertSame([
            'products' => 1,
            'services' => 1,
            'archived' => 1,
            'inventoryTracked' => 1,
        ], $repository->summary($this->organizationUid));
    }
}
