<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Health\EntitiesHealthCheck;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;

final class EntitiesHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_when_every_record_has_a_live_definition(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $recordRepository = new EntityRecordRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $records = new EntityRecordService($definitions, $fields, $recordRepository);

        $definition = $builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle');
        $records->create($definition->uid->toString(), []);

        $result = (new EntitiesHealthCheck($this->pdo, $recordRepository))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeDefinitions']);
        $this->assertSame(0, $result->details['orphanedRecords']);
    }

    public function test_warning_when_a_record_references_an_archived_definition(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $recordRepository = new EntityRecordRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $records = new EntityRecordService($definitions, $fields, $recordRepository);

        $definition = $builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle');
        $records->create($definition->uid->toString(), []);
        $definitions->archive($definition->uid->toString());

        $result = (new EntitiesHealthCheck($this->pdo, $recordRepository))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['orphanedRecords']);
    }
}
