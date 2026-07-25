<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;

final class CategoryRepositoryTest extends DatabaseTestCase
{
    private function repository(): CategoryRepository
    {
        return new CategoryRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');

        $repository->save($category);
        $found = $repository->find($category->uid->toString());

        $this->assertNotNull($found);
        $this->assertSame('Travel', $found->name);
        $this->assertTrue($found->isActive());
    }

    public function test_for_organization_orders_by_name(): void
    {
        $repository = $this->repository();
        $repository->save(ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel'));
        $repository->save(ExpenseCategory::create($this->organizationUid, 'MEALS', 'Meals'));

        $names = array_map(fn ($c) => $c->name, $repository->forOrganization($this->organizationUid));

        $this->assertSame(['Meals', 'Travel'], $names);
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $repository->save($category);

        $repository->archive($category->uid->toString());
        $repository->restore($category->uid->toString());

        $this->assertNotNull($repository->find($category->uid->toString()));
    }
}
