<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntitySchemaService;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;

final class EntitySchemaServiceTest extends DatabaseTestCase
{
    public function test_describe_returns_the_full_field_contract(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $schema = new EntitySchemaService($definitions, $fields);

        $definition = $builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle', apiExposed: true);
        $builder->addField($definition->uid->toString(), 'plate', 'Plate number', 'string', required: true);

        $described = $schema->describe($definition->uid->toString());

        $this->assertSame('vehicle', $described['key']);
        $this->assertTrue($described['apiExposed']);
        $this->assertCount(1, $described['fields']);
        $this->assertSame('plate', $described['fields'][0]['key']);
        $this->assertTrue($described['fields'][0]['required']);
    }

    public function test_describe_exposed_only_returns_api_exposed_active_definitions(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $builder = new EntityBuilderService($definitions, $fields);
        $schema = new EntitySchemaService($definitions, $fields);

        $builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle', apiExposed: true);
        $builder->defineEntity($this->organizationUid, 'internal_note', 'Internal Note', apiExposed: false);

        $exposed = $schema->describeExposed($this->organizationUid);

        $this->assertCount(1, $exposed);
        $this->assertSame('vehicle', $exposed[0]['key']);
    }
}
