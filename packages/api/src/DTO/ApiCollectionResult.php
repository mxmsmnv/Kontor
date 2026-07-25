<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

/**
 * A resource's answer to `list()` — kontor.md#20.4's pagination meta
 * (`total`/`totalPages`) is computed from `$total` by the response layer,
 * not by the resource itself.
 */
final class ApiCollectionResult
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
    ) {
    }
}
