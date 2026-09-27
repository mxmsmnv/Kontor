<?php

declare(strict_types=1);

namespace Kontor\Queue\Infrastructure\Persistence;

/**
 * Persistence contract for kontor_jobs (kontor.md#11.5). JobRepository is
 * the MySQL-backed implementation; Queue/QueueWorker depend on this
 * interface so their control flow (retry vs. dead-letter, progress
 * reporting) can be unit-tested against an in-memory fake.
 */
interface JobRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return string the job's uid
     */
    public function enqueue(
        string $queue,
        string $jobType,
        array $payload,
        int $priority,
        int $maxAttempts,
        \DateTimeImmutable $availableAt,
        ?string $idempotencyKey,
    ): string;

    /**
     * @return array<string, mixed>|null
     */
    public function reserveNext(string $queue): ?array;

    public function markCompleted(string $uid): void;

    public function markForRetry(string $uid, string $errorMessage, \DateTimeImmutable $nextAvailableAt): void;

    public function markDead(string $uid, string $errorMessage): void;

    public function updateProgress(string $uid, int $percent): void;

    public function cancel(string $uid): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $uid): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $queue = null, ?string $status = null): array;

    public function deadLetterCount(): int;

    public function stuckReservedCount(int $thresholdSeconds): int;
}
