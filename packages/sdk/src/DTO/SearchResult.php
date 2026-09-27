<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class SearchResult
{
    /**
     * @param SearchHit[] $hits
     */
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
    ) {
    }
}
