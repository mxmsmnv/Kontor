<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\DTO\ApiResourceSchema;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;

/**
 * The "OpenAPI" milestone (kontor.md#20). Generates an OpenAPI 3.0
 * document straight from whatever is currently registered in
 * `ApiResourceRegistry` — pure, no HTTP/DB dependency, so it runs as a
 * real unit test and always reflects exactly what the router will
 * actually accept.
 */
final class OpenApiGenerator
{
    /**
     * @var array<string, array{type: string, format?: string, description?: string, items?: array}>
     */
    private const TYPE_MAP = [
        'string' => ['type' => 'string'],
        'int' => ['type' => 'integer'],
        'decimal' => ['type' => 'number'],
        'bool' => ['type' => 'boolean'],
        'date' => ['type' => 'string', 'format' => 'date'],
        'datetime' => ['type' => 'string', 'format' => 'date-time'],
        'money' => ['type' => 'integer', 'description' => 'Minor currency units'],
        'array' => ['type' => 'array', 'items' => ['type' => 'string']],
    ];

    public function __construct(
        private readonly ApiResourceRegistry $resources,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $paths = [];
        $schemas = [];

        foreach ($this->resources->all() as $resource) {
            $key = $resource->key();
            $schema = $resource->schema();
            $schemaName = ucfirst($key);

            $schemas[$schemaName] = $this->schemaObject($schema);

            $paths["/{$key}"] = array_filter([
                'get' => $this->listOperation($key, $schemaName),
                'post' => $schema->supportsCreate ? $this->createOperation($key, $schemaName) : null,
            ]);

            $paths["/{$key}/{uid}"] = array_filter([
                'get' => $this->findOperation($key, $schemaName),
                'patch' => $schema->supportsUpdate ? $this->updateOperation($key, $schemaName) : null,
                'delete' => $schema->supportsDelete ? $this->deleteOperation($key) : null,
            ]);
        }

        return [
            'openapi' => '3.0.3',
            'info' => ['title' => 'Kontor API', 'version' => 'v1'],
            'servers' => [['url' => '/api/kontor/v1']],
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas,
                'securitySchemes' => [
                    'bearerToken' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
            'security' => [['bearerToken' => []]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaObject(ApiResourceSchema $schema): array
    {
        $properties = [];

        foreach ($schema->fields as $field => $type) {
            $properties[$field] = self::TYPE_MAP[$type] ?? ['type' => 'string'];
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @return array<string, mixed>
     */
    private function listOperation(string $key, string $schemaName): array
    {
        return [
            'summary' => "List {$key}",
            'parameters' => [
                ['name' => 'page[number]', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ['name' => 'page[size]', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'include', 'in' => 'query', 'schema' => ['type' => 'string']],
            ],
            'responses' => [
                '200' => [
                    'description' => 'OK',
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
                    ]]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createOperation(string $key, string $schemaName): array
    {
        return [
            'summary' => "Create a {$key} resource",
            'parameters' => [
                ['name' => 'Idempotency-Key', 'in' => 'header', 'schema' => ['type' => 'string']],
            ],
            'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]]],
            'responses' => ['201' => ['description' => 'Created']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOperation(string $key, string $schemaName): array
    {
        return [
            'summary' => "Get a single {$key} resource",
            'parameters' => [['name' => 'uid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            'responses' => [
                '200' => ['content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]]],
                '404' => ['description' => 'Not found'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateOperation(string $key, string $schemaName): array
    {
        return [
            'summary' => "Update a {$key} resource",
            'parameters' => [['name' => 'uid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]]],
            'responses' => ['200' => ['description' => 'OK']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteOperation(string $key): array
    {
        return [
            'summary' => "Delete a {$key} resource",
            'parameters' => [['name' => 'uid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            'responses' => ['204' => ['description' => 'Deleted']],
        ];
    }
}
