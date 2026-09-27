<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Support;

use Kontor\Queue\Infrastructure\Persistence\JobRepositoryInterface;

/**
 * In-memory double mirroring JobRepository's exact row shape, so
 * QueueWorker/Queue can be unit-tested without a real MySQL connection.
 */
final class InMemoryJobRepository implements JobRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];
    private int $nextId = 1;

    public function enqueue(
        string $queue,
        string $jobType,
        array $payload,
        int $priority,
        int $maxAttempts,
        \DateTimeImmutable $availableAt,
        ?string $idempotencyKey,
    ): string {
        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $existing['uid'];
            }
        }

        $uid = 'job_' . $this->nextId;

        $this->rows[$uid] = [
            'id' => $this->nextId,
            'uid' => $uid,
            'queue' => $queue,
            'job_type' => $jobType,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'priority' => $priority,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'available_at' => $this->format($availableAt),
            'started_at' => null,
            'finished_at' => null,
            'failed_at' => null,
            'progress' => 0,
            'idempotency_key' => $idempotencyKey,
            'error_message' => null,
            'created_at' => $this->format(new \DateTimeImmutable()),
        ];

        $this->nextId++;

        return $uid;
    }

    public function reserveNext(string $queue): ?array
    {
        $now = $this->format(new \DateTimeImmutable());

        $candidates = array_filter(
            $this->rows,
            static fn (array $row): bool => $row['queue'] === $queue && $row['status'] === 'pending' && $row['available_at'] <= $now
        );

        if ($candidates === []) {
            return null;
        }

        uasort(
            $candidates,
            static fn (array $a, array $b): int => $b['priority'] <=> $a['priority'] ?: $a['available_at'] <=> $b['available_at']
        );

        $uid = array_key_first($candidates);

        $this->rows[$uid]['status'] = 'reserved';
        $this->rows[$uid]['started_at'] = $now;
        $this->rows[$uid]['attempts']++;

        return $this->rows[$uid];
    }

    public function markCompleted(string $uid): void
    {
        $this->rows[$uid]['status'] = 'completed';
        $this->rows[$uid]['progress'] = 100;
        $this->rows[$uid]['finished_at'] = $this->format(new \DateTimeImmutable());
        $this->rows[$uid]['error_message'] = null;
    }

    public function markForRetry(string $uid, string $errorMessage, \DateTimeImmutable $nextAvailableAt): void
    {
        $this->rows[$uid]['status'] = 'pending';
        $this->rows[$uid]['available_at'] = $this->format($nextAvailableAt);
        $this->rows[$uid]['error_message'] = $errorMessage;
    }

    public function markDead(string $uid, string $errorMessage): void
    {
        $this->rows[$uid]['status'] = 'dead';
        $this->rows[$uid]['failed_at'] = $this->format(new \DateTimeImmutable());
        $this->rows[$uid]['error_message'] = $errorMessage;
    }

    public function updateProgress(string $uid, int $percent): void
    {
        $this->rows[$uid]['progress'] = max(0, min(100, $percent));
    }

    public function cancel(string $uid): bool
    {
        if (($this->rows[$uid]['status'] ?? null) !== 'pending') {
            return false;
        }

        $this->rows[$uid]['status'] = 'cancelled';

        return true;
    }

    public function find(string $uid): ?array
    {
        return $this->rows[$uid] ?? null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['idempotency_key'] === $idempotencyKey) {
                return $row;
            }
        }

        return null;
    }

    public function all(?string $queue = null, ?string $status = null): array
    {
        return array_values(array_filter(
            $this->rows,
            static function (array $row) use ($queue, $status): bool {
                if ($queue !== null && $row['queue'] !== $queue) {
                    return false;
                }

                return !($status !== null && $row['status'] !== $status);
            }
        ));
    }

    public function deadLetterCount(): int
    {
        return count(array_filter($this->rows, static fn (array $row): bool => $row['status'] === 'dead'));
    }

    public function stuckReservedCount(int $thresholdSeconds): int
    {
        $cutoff = $this->format((new \DateTimeImmutable())->modify("-{$thresholdSeconds} seconds"));

        return count(array_filter(
            $this->rows,
            static fn (array $row): bool => $row['status'] === 'reserved' && $row['started_at'] !== null && $row['started_at'] <= $cutoff
        ));
    }

    private function format(\DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.u');
    }
}
