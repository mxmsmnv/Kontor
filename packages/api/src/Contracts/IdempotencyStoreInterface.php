<?php

declare(strict_types=1);

namespace Kontor\API\Contracts;

use Kontor\API\DTO\StoredIdempotentResponse;

/**
 * The persistence boundary `IdempotencyService` depends on, so its
 * conflict-detection logic is unit-testable with a fake store instead of
 * a live database — the same reasoning behind `HttpClientInterface` for
 * webhook delivery.
 */
interface IdempotencyStoreInterface
{
    /**
     * @return array{response: StoredIdempotentResponse, requestFingerprint: string}|null
     */
    public function find(string $organizationUid, string $idempotencyKey): ?array;

    /**
     * @param array<string, mixed> $responseBody
     */
    public function store(
        string $organizationUid,
        string $idempotencyKey,
        string $requestFingerprint,
        int $responseStatus,
        array $responseBody,
        ?\DateTimeImmutable $expiresAt = null,
    ): void;
}
