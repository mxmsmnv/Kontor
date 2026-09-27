<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\IdempotencyKeyConflictException;
use Kontor\API\Application\IdempotencyService;
use Kontor\API\Contracts\IdempotencyStoreInterface;
use Kontor\API\DTO\StoredIdempotentResponse;
use PHPUnit\Framework\TestCase;

final class IdempotencyServiceTest extends TestCase
{
    public function test_first_call_executes_the_operation_and_stores_the_result(): void
    {
        $store = new class implements IdempotencyStoreInterface {
            public array $stored = [];

            public function find(string $organizationUid, string $idempotencyKey): ?array
            {
                return null;
            }

            public function store(string $organizationUid, string $idempotencyKey, string $requestFingerprint, int $responseStatus, array $responseBody, ?\DateTimeImmutable $expiresAt = null): void
            {
                $this->stored[] = compact('organizationUid', 'idempotencyKey', 'requestFingerprint', 'responseStatus', 'responseBody');
            }
        };

        $calls = 0;
        $service = new IdempotencyService($store);

        $result = $service->remember('org_1', 'key-1', ['amount' => 100], function () use (&$calls): array {
            $calls++;

            return ['status' => 201, 'body' => ['uid' => 'inv_1']];
        });

        $this->assertSame(1, $calls);
        $this->assertSame(201, $result->status);
        $this->assertSame(['uid' => 'inv_1'], $result->body);
        $this->assertCount(1, $store->stored);
    }

    public function test_retry_with_the_same_body_returns_the_cached_response_without_calling_the_operation_again(): void
    {
        $cachedFingerprint = hash('sha256', json_encode(['amount' => 100], JSON_THROW_ON_ERROR));

        $store = new class($cachedFingerprint) implements IdempotencyStoreInterface {
            public function __construct(private readonly string $fingerprint)
            {
            }

            public function find(string $organizationUid, string $idempotencyKey): ?array
            {
                return [
                    'response' => new StoredIdempotentResponse(201, ['uid' => 'inv_1']),
                    'requestFingerprint' => $this->fingerprint,
                ];
            }

            public function store(string $organizationUid, string $idempotencyKey, string $requestFingerprint, int $responseStatus, array $responseBody, ?\DateTimeImmutable $expiresAt = null): void
            {
                throw new \LogicException('store() must not be called on a cache hit.');
            }
        };

        $calls = 0;
        $service = new IdempotencyService($store);

        $result = $service->remember('org_1', 'key-1', ['amount' => 100], function () use (&$calls): array {
            $calls++;

            return ['status' => 201, 'body' => ['uid' => 'should-not-happen']];
        });

        $this->assertSame(0, $calls);
        $this->assertSame(['uid' => 'inv_1'], $result->body);
    }

    public function test_reusing_a_key_with_a_different_body_is_a_conflict(): void
    {
        $store = new class implements IdempotencyStoreInterface {
            public function find(string $organizationUid, string $idempotencyKey): ?array
            {
                return [
                    'response' => new StoredIdempotentResponse(201, ['uid' => 'inv_1']),
                    'requestFingerprint' => hash('sha256', json_encode(['amount' => 100], JSON_THROW_ON_ERROR)),
                ];
            }

            public function store(string $organizationUid, string $idempotencyKey, string $requestFingerprint, int $responseStatus, array $responseBody, ?\DateTimeImmutable $expiresAt = null): void
            {
            }
        };

        $service = new IdempotencyService($store);

        $this->expectException(IdempotencyKeyConflictException::class);

        $service->remember('org_1', 'key-1', ['amount' => 999], fn (): array => ['status' => 201, 'body' => []]);
    }
}
