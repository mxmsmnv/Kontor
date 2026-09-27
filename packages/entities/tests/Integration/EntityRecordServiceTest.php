<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;

final class EntityRecordServiceTest extends DatabaseTestCase
{
    private EntityBuilderService $builder;
    private EntityRecordService $records;
    private string $definitionUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new EntityDefinitionRepository($this->pdo, $organizations);
        $fields = new EntityFieldRepository($this->pdo, $organizations);
        $recordRepository = new EntityRecordRepository($this->pdo, $organizations);

        $this->builder = new EntityBuilderService($definitions, $fields);
        $this->records = new EntityRecordService($definitions, $fields, $recordRepository);

        $definition = $this->builder->defineEntity($this->organizationUid, 'vehicle', 'Vehicle');
        $this->definitionUid = $definition->uid->toString();
        $this->builder->addField($this->definitionUid, 'plate', 'Plate number', 'string', required: true);
        $this->builder->addField($this->definitionUid, 'mileage', 'Mileage', 'int', required: false);
    }

    public function test_create_a_valid_record(): void
    {
        $record = $this->records->create($this->definitionUid, ['plate' => 'AB-123-CD', 'mileage' => 50000]);

        $this->assertSame('AB-123-CD', $record->data['plate']);
        $this->assertSame($this->organizationUid, $record->organizationId);
    }

    public function test_create_rejects_a_missing_required_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->records->create($this->definitionUid, ['mileage' => 50000]);
    }

    public function test_create_rejects_a_wrong_typed_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->records->create($this->definitionUid, ['plate' => 'AB-123-CD', 'mileage' => 'a lot']);
    }

    public function test_create_rejects_an_undeclared_field_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->records->create($this->definitionUid, ['plate' => 'AB-123-CD', 'color' => 'red']);
    }

    public function test_update_re_validates(): void
    {
        $record = $this->records->create($this->definitionUid, ['plate' => 'AB-123-CD']);

        $updated = $this->records->update($record->uid->toString(), ['plate' => 'XY-999-ZZ', 'mileage' => 60000]);

        $this->assertSame('XY-999-ZZ', $updated->data['plate']);
        $this->assertSame(60000, $updated->data['mileage']);

        $this->expectException(\InvalidArgumentException::class);
        $this->records->update($record->uid->toString(), []);
    }

    public function test_add_field_rejects_an_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->addField($this->definitionUid, 'weird', 'Weird', 'array');
    }
}
