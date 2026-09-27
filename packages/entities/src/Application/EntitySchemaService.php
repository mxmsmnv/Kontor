<?php

declare(strict_types=1);

namespace Kontor\Entities\Application;

use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;

/**
 * The "API exposure" milestone. Stage 8 (kontor.md#36 — REST API,
 * GraphQL, Marketplace) doesn't exist yet, so this can't mean real HTTP
 * endpoints; it means shaping the data contract those future layers would
 * consume, and marking which entities opt into it. describe() is exactly
 * what a REST/GraphQL schema generator would call to build an endpoint or
 * type for one custom entity — no admin UI/API server is built here.
 */
final class EntitySchemaService
{
    public function __construct(
        private readonly EntityDefinitionRepository $definitions,
        private readonly EntityFieldRepository $fields,
    ) {
    }

    /**
     * @return array{key: string, name: string, apiExposed: bool, fields: array<int, array{key: string, label: string, type: string, required: bool}>}
     */
    public function describe(string $definitionUid): array
    {
        $definition = $this->definitions->require($definitionUid);

        return [
            'key' => $definition->entityKey,
            'name' => $definition->name,
            'apiExposed' => $definition->apiExposed,
            'fields' => array_map(
                static fn ($field): array => [
                    'key' => $field->fieldKey,
                    'label' => $field->label,
                    'type' => $field->fieldType,
                    'required' => $field->required,
                ],
                $this->fields->forDefinition($definitionUid),
            ),
        ];
    }

    /**
     * Every schema marked api_exposed for an organization — what a future
     * REST/GraphQL layer would enumerate to build its routes/types from.
     *
     * @return array<int, array{key: string, name: string, apiExposed: bool, fields: array<int, array<string, mixed>>}>
     */
    public function describeExposed(string $organizationUid): array
    {
        $exposed = array_filter(
            $this->definitions->forOrganization($organizationUid),
            static fn ($definition): bool => $definition->apiExposed && $definition->isActive(),
        );

        return array_values(array_map(fn ($definition) => $this->describe($definition->uid->toString()), $exposed));
    }
}
