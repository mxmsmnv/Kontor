<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ReportSchema
{
    /**
     * @param array<string, string> $fields field key => scalar type (string|int|decimal|date|money)
     * @param string[] $filterableFields
     * @param string[] $groupableFields
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $filterableFields = [],
        public readonly array $groupableFields = [],
    ) {
    }
}
