<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Application\EntityRelationService;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;

final class EntityRelationServiceTest extends DatabaseTestCase
{
    public function test_link_records_reuses_cores_relation_repository(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $records = new EntityRecordService($definitions, $fields, new EntityRecordRepository($this->pdo, $organizations));
        $relations = new EntityRelationService(new RelationRepository($this->pdo, $organizations));

        $definition = $builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle');
        $record = $records->create($definition->uid->toString(), []);

        $relations->linkRecords($this->organizationUid, 'vehicle', $record->uid->toString(), 'contact', 'ct_01');

        $related = $relations->relatedTo($this->organizationUid, 'vehicle', $record->uid->toString());
        $this->assertCount(1, $related);
        $this->assertSame('contact', $related[0]['targetType']);
    }
}
