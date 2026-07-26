<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CategoryRepositoryTest extends DatabaseTestCase
{
    private function repository(): CategoryRepository
    {
        return new CategoryRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $category = Category::create($this->organizationUid, ['en' => 'Electronics', 'de' => 'Elektronik']);

        $repository->save($category);
        $found = $repository->find($category->uid->toString());

        $this->assertSame('Elektronik', $found->nameIn('de'));
    }

    public function test_children_and_roots(): void
    {
        $repository = $this->repository();
        $parent = Category::create($this->organizationUid, ['en' => 'Electronics']);
        $repository->save($parent);

        $child = Category::create($this->organizationUid, ['en' => 'Laptops'], parentUid: $parent->uid->toString());
        $repository->save($child);

        $roots = $repository->roots($this->organizationUid);
        $this->assertCount(1, $roots);
        $this->assertSame($parent->uid->toString(), $roots[0]->uid->toString());

        $children = $repository->children($parent->uid->toString());
        $this->assertCount(1, $children);
        $this->assertSame('Laptops', $children[0]->nameIn('en'));
    }

    public function test_archive(): void
    {
        $repository = $this->repository();
        $category = Category::create($this->organizationUid, ['en' => 'Electronics']);
        $repository->save($category);

        $repository->archive($category->uid->toString());

        $row = $this->pdo->query('SELECT archived_at FROM kontor_catalog_categories')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $this->assertSame(0, $repository->countMatching($this->organizationUid, 'Electronics'));
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Electronics', true));
        $repository->restore($category->uid->toString());
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Electronics'));
    }

    public function test_list_search_and_pagination(): void
    {
        $repository = $this->repository();
        $repository->save(Category::create($this->organizationUid, ['en' => 'Nebula Hardware'], sortOrder: 10));
        $repository->save(Category::create($this->organizationUid, ['en' => 'Nebula Services'], sortOrder: 20));

        $this->assertSame(2, $repository->countMatching($this->organizationUid, 'Nebula'));
        $first = $repository->findAll($this->organizationUid, 'Nebula', limit: 1);
        $second = $repository->findAll($this->organizationUid, 'Nebula', limit: 1, offset: 1);
        $this->assertNotSame($first[0]->uid->toString(), $second[0]->uid->toString());
        $this->assertSame($first[0]->uid->toString(), $repository->require($first[0]->uid->toString())->uid->toString());
    }

    public function test_status_filter_returns_only_matching_categories(): void
    {
        $repository = $this->repository();
        $active = Category::create($this->organizationUid, ['en' => 'Active category']);
        $inactive = Category::create(
            $this->organizationUid,
            ['en' => 'Inactive category'],
            status: 'inactive',
        );
        $repository->save($active);
        $repository->save($inactive);

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
    }

    public function test_bulk_archive_and_restore_are_tenant_scoped_and_idempotent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $repository = new CategoryRepository($this->pdo, $organizations);
        $first = Category::create($this->organizationUid, ['en' => 'First']);
        $second = Category::create($this->organizationUid, ['en' => 'Second']);
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = Category::create(
            $otherOrganization->uid->toString(),
            ['en' => 'Other tenant category'],
        );

        foreach ([$first, $second, $other] as $category) {
            $repository->save($category);
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
            $archived,
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
        $repository = new CategoryRepository($this->pdo, $organizations);
        $first = Category::create($this->organizationUid, ['en' => 'First']);
        $second = Category::create($this->organizationUid, ['en' => 'Second']);
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = Category::create(
            $otherOrganization->uid->toString(),
            ['en' => 'Other category'],
        );

        foreach ([$first, $second, $other] as $category) {
            $repository->save($category);
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
