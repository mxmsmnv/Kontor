<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

/**
 * A previously cached response for a given `Idempotency-Key`
 * (kontor.md#20.9) — see `IdempotencyService`.
 */
final class StoredIdempotentResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {
    }
}
