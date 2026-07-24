<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class SearchQuery
{
    /**
     * @param string[] $entityTypes
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $term,
        public readonly array $entityTypes = [],
        public readonly int $limit = 20,
        public readonly int $offset = 0,
    ) {
    }
}
