<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
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
}
