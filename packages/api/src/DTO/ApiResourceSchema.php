<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

/**
 * A resource's shape, for the "OpenAPI" milestone's generator to read —
 * the same small scalar-type vocabulary `Kontor\SDK\DTO\ReportSchema`
 * uses for report fields (`string`/`int`/`decimal`/`bool`/`date`/
 * `datetime`/`money`/`array`).
 */
final class ApiResourceSchema
{
    /**
     * @param array<string, string> $fields field key => scalar type
     * @param string[] $filterableFields
     * @param string[] $sortableFields
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $filterableFields = [],
        public readonly array $sortableFields = [],
        public readonly bool $supportsCreate = true,
        public readonly bool $supportsUpdate = true,
        public readonly bool $supportsDelete = true,
    ) {
    }
}
