<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\API\DTO\ApiResourceSchema;

/**
 * The "component types" milestone: translates an `ApiResourceSchema`
 * scalar-type map (`kontor/api`'s own small vocabulary — `string`/`int`/
 * `decimal`/`bool`/`date`/`datetime`/`money`/`array`) into GraphQL scalar
 * type names. Pure.
 */
final class GraphQLTypeMapper
{
    private const TYPE_MAP = [
        'string' => 'String',
        'int' => 'Int',
        'decimal' => 'Float',
        'bool' => 'Boolean',
        'date' => 'String',
        'datetime' => 'String',
        'money' => 'Int',
        'array' => '[String]',
    ];

    public function scalarType(string $kontorType): string
    {
        return self::TYPE_MAP[$kontorType] ?? 'String';
    }

    /**
     * @return array<string, string> field name => GraphQL scalar type
     */
    public function objectFields(ApiResourceSchema $schema): array
    {
        $fields = [];

        foreach ($schema->fields as $field => $type) {
            $fields[$field] = $this->scalarType($type);
        }

        return $fields;
    }
}
