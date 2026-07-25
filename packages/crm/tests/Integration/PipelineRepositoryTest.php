<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class PipelineRepositoryTest extends DatabaseTestCase
{
    public function test_save_then_find_round_trips(): void
    {
        $repository = new PipelineRepository($this->pdo, new OrganizationRepository($this->pdo));
        $pipeline = Pipeline::create($this->organizationUid, 'Sales', isDefault: true);

        $repository->save($pipeline);
        $found = $repository->find($pipeline->uid->toString());

        $this->assertSame('Sales', $found->name);
        $this->assertTrue($found->isDefault);
    }

    public function test_default_for_entity_type(): void
    {
        $repository = new PipelineRepository($this->pdo, new OrganizationRepository($this->pdo));
        $repository->save(Pipeline::create($this->organizationUid, 'Secondary', isDefault: false));
        $default = Pipeline::create($this->organizationUid, 'Primary', isDefault: true);
        $repository->save($default);

        $found = $repository->defaultForEntityType($this->organizationUid, 'deal');

        $this->assertSame($default->uid->toString(), $found->uid->toString());
    }

    public function test_default_for_entity_type_returns_null_when_none_configured(): void
    {
        $repository = new PipelineRepository($this->pdo, new OrganizationRepository($this->pdo));

        $this->assertNull($repository->defaultForEntityType($this->organizationUid, 'deal'));
    }
}
