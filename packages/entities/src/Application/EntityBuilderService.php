<?php

declare(strict_types=1);

namespace Kontor\Entities\Application;

use Kontor\Entities\Domain\EntityDefinition;
use Kontor\Entities\Domain\EntityField;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;

/**
 * The "entity builder" and "fields" milestones' authoring side.
 * EntityField::create() itself validates fieldType is one of the
 * supported scalar types (kontor.md-style: string/int/decimal/bool/
 * date/datetime) — this service just wires the definition lookup.
 */
final class EntityBuilderService
{
    public function __construct(
        private readonly EntityDefinitionRepository $definitions,
        private readonly EntityFieldRepository $fields,
    ) {
    }

    public function defineEntity(
        string $organizationUid,
        string $entityKey,
        string $name,
        ?string $viewPermission = null,
        ?string $editPermission = null,
        bool $apiExposed = false,
        ?int $createdBy = null,
    ): EntityDefinition {
        $definition = EntityDefinition::create($organizationUid, $entityKey, $name, $viewPermission, $editPermission, $apiExposed, $createdBy);
        $this->definitions->save($definition);

        return $definition;
    }

    public function addField(string $definitionUid, string $fieldKey, string $label, string $fieldType, bool $required = false, int $sortOrder = 0): EntityField
    {
        $definition = $this->definitions->require($definitionUid);
        $field = EntityField::create($definition->organizationId, $definitionUid, $fieldKey, $label, $fieldType, $required, $sortOrder);
        $this->fields->save($field);

        return $field;
    }
}
