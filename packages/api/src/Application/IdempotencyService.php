<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\Contracts\IdempotencyStoreInterface;
use Kontor\API\DTO\StoredIdempotentResponse;

/**
 * The "idempotency" milestone (kontor.md#20.9). Wraps a single critical
 * create operation: a first call executes `$operation` and caches its
 * result; a retry with the same `Idempotency-Key` and the same request
 * body returns the cached result without calling `$operation` again. A
 * retry with the same key but a *different* body is a client error
 * (reusing a key for a different request), not silently served from
 * cache.
 */
final class IdempotencyService
{
    public function __construct(
        private readonly IdempotencyStoreInterface $keys,
    ) {
    }

    /**
     * @param array<string, mixed> $requestBody
     * @param callable(): array{status: int, body: array<string, mixed>} $operation
     *
     * @throws IdempotencyKeyConflictException
     */
    public function remember(
        string $organizationUid,
        string $idempotencyKey,
        array $requestBody,
        callable $operation,
        ?\DateTimeImmutable $expiresAt = null,
    ): StoredIdempotentResponse {
        $fingerprint = $this->fingerprint($requestBody);
        $existing = $this->keys->find($organizationUid, $idempotencyKey);

        if ($existing !== null) {
            if (!hash_equals($existing['requestFingerprint'], $fingerprint)) {
                throw new IdempotencyKeyConflictException(
                    "Idempotency-Key \"{$idempotencyKey}\" was already used for a different request."
                );
            }

            return $existing['response'];
        }

        $result = $operation();

        $this->keys->store($organizationUid, $idempotencyKey, $fingerprint, $result['status'], $result['body'], $expiresAt);

        return new StoredIdempotentResponse($result['status'], $result['body']);
    }

    /**
     * @param array<string, mixed> $requestBody
     */
    private function fingerprint(array $requestBody): string
    {
        return hash('sha256', json_encode($requestBody, JSON_THROW_ON_ERROR));
    }
}
