<?php

declare(strict_types=1);

namespace Kontor\API\Application;

/**
 * The "sparse fields" half of the filtering milestone (kontor.md#20.7).
 * Pure — applied generically to any resource's presented row, so
 * individual `ApiResourceInterface` implementations don't each reimplement
 * projection.
 */
final class ApiFieldProjector
{
    /**
     * @param array<string, mixed> $row
     * @param string[]|null $fields null returns the row unchanged
     * @return array<string, mixed>
     */
    public function project(array $row, ?array $fields): array
    {
        if ($fields === null) {
            return $row;
        }

        return array_intersect_key($row, array_flip($fields));
    }
}
