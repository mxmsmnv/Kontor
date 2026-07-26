<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Integration;

use Kontor\API\DTO\ApiQuery;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Infrastructure\API\CustomEntityResource;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;

final class CustomEntityResourceTest extends DatabaseTestCase
{
    public function test_crud_flows_through_the_declared_custom_schema(): void
    {
        [$resource] = $this->resource();

        $created = $resource->create($this->organizationUid, [
            'vin' => 'QA-123',
            'mileage' => 1200,
        ]);
        $listed = $resource->list($this->organizationUid, new ApiQuery());

        $this->assertSame('entities_vehicle', $resource->key());
        $this->assertSame('string', $resource->schema()->fields['vin']);
        $this->assertSame('int', $resource->schema()->fields['mileage']);
        $this->assertSame(1, $listed->total);
        $this->assertSame('QA-123', $listed->rows[0]['vin']);

        $updated = $resource->update(
            $this->organizationUid,
            $created['uid'],
            ['mileage' => 1400],
        );
        $this->assertSame(1400, $updated['mileage']);
        $this->assertSame('QA-123', $updated['vin']);

        $resource->delete($this->organizationUid, $created['uid']);
        $this->assertNull($resource->find($this->organizationUid, $created['uid']));
    }

    public function test_another_organization_cannot_read_the_record(): void
    {
        [$resource, $organizations] = $this->resource();
        $created = $resource->create($this->organizationUid, ['vin' => 'ORG-A', 'mileage' => 1]);
        $other = Organization::createDefault('US', 'en', 'EUR');
        $other->name = 'Other';
        $organizations->save($other);

        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $otherDefinition = $builder->defineEntity(
            $other->uid->toString(),
            'vehicle',
            'Vehicle',
            apiExposed: true,
        );
        $builder->addField($otherDefinition->uid->toString(), 'vin', 'VIN', 'string', required: true);
        $builder->addField($otherDefinition->uid->toString(), 'mileage', 'Mileage', 'int');

        $this->assertNull($resource->find($other->uid->toString(), $created['uid']));
    }

    /**
     * @return array{CustomEntityResource, OrganizationRepository}
     */
    private function resource(): array
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $records = new EntityRecordRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $recordService = new EntityRecordService($definitions, $fields, $records);
        $definition = $builder->defineEntity(
            $this->organizationUid,
            'vehicle',
            'Vehicle',
            apiExposed: true,
        );
        $builder->addField($definition->uid->toString(), 'vin', 'VIN', 'string', required: true);
        $builder->addField($definition->uid->toString(), 'mileage', 'Mileage', 'int');

        return [
            new CustomEntityResource(
                'vehicle',
                ['vin' => 'string', 'mileage' => 'int'],
                $definitions,
                $records,
                $recordService,
            ),
            $organizations,
        ];
    }
}
