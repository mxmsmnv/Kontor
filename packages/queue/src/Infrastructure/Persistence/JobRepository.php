<?php

declare(strict_types=1);

namespace Kontor\Queue\Infrastructure\Persistence;

use Kontor\SDK\ValueObjects\Uid;

/**
 * PDO-backed persistence for kontor_jobs (kontor.md#11.5). Reservation
 * uses `SELECT ... FOR UPDATE SKIP LOCKED` inside a transaction so multiple
 * concurrent workers never grab the same job (MySQL 8.0.1+, matching
 * kontor.md#10.1's primary database target).
 */
final class JobRepository implements JobRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return string the job's uid — an existing job's uid if idempotencyKey
     *   already matched any prior job (kontor.md#20.9: the same idempotency
     *   key always yields the same result, regardless of the original job's
     *   current status — it is also the only way to avoid violating the
     *   column's UNIQUE constraint on a second dispatch)
     */
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

        $uid = Uid::generate()->toString();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_jobs
                (uid, queue, job_type, payload_json, priority, status, attempts, max_attempts,
                 available_at, progress, idempotency_key, created_at)
             VALUES
                (:uid, :queue, :job_type, :payload_json, :priority, :status, 0, :max_attempts,
                 :available_at, 0, :idempotency_key, :created_at)'
        );

        $statement->execute([
            'uid' => $uid,
            'queue' => $queue,
            'job_type' => $jobType,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'priority' => $priority,
            'status' => 'pending',
            'max_attempts' => $maxAttempts,
            'available_at' => $this->format($availableAt),
            'idempotency_key' => $idempotencyKey,
            'created_at' => $this->format(new \DateTimeImmutable()),
        ]);

        return $uid;
    }

    /**
     * Atomically claims the next due job in $queue, or null if none is
     * available right now.
     *
     * @return array<string, mixed>|null
     */
    public function reserveNext(string $queue): ?array
    {
        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                "SELECT * FROM kontor_jobs
                 WHERE queue = :queue AND status = 'pending' AND available_at <= :now
                 ORDER BY priority DESC, available_at ASC
                 LIMIT 1 FOR UPDATE SKIP LOCKED"
            );
            $statement->execute(['queue' => $queue, 'now' => $this->format(new \DateTimeImmutable())]);
            $job = $statement->fetch(\PDO::FETCH_ASSOC);

            if ($job === false) {
                if (!$wasInTransaction) {
                    $this->pdo->commit();
                }

                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE kontor_jobs
                 SET status = 'reserved', started_at = :now, attempts = attempts + 1
                 WHERE id = :id"
            );
            $update->execute(['now' => $this->format(new \DateTimeImmutable()), 'id' => $job['id']]);

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }

            $job['status'] = 'reserved';
            $job['attempts'] = ((int) $job['attempts']) + 1;

            return $job;
        } catch (\Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function markCompleted(string $uid): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE kontor_jobs SET status = 'completed', progress = 100, finished_at = :now, error_message = NULL
             WHERE uid = :uid"
        );
        $statement->execute(['now' => $this->format(new \DateTimeImmutable()), 'uid' => $uid]);
    }

    public function markForRetry(string $uid, string $errorMessage, \DateTimeImmutable $nextAvailableAt): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE kontor_jobs
             SET status = 'pending', available_at = :available_at, error_message = :error_message
             WHERE uid = :uid"
        );
        $statement->execute([
            'available_at' => $this->format($nextAvailableAt),
            'error_message' => $errorMessage,
            'uid' => $uid,
        ]);
    }

    /**
     * Moves the job to the dead-letter state: it will never be picked up
     * again without manual intervention (kontor.md section 31).
     */
    public function markDead(string $uid, string $errorMessage): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE kontor_jobs SET status = 'dead', failed_at = :now, error_message = :error_message
             WHERE uid = :uid"
        );
        $statement->execute([
            'now' => $this->format(new \DateTimeImmutable()),
            'error_message' => $errorMessage,
            'uid' => $uid,
        ]);
    }

    public function updateProgress(string $uid, int $percent): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_jobs SET progress = :progress WHERE uid = :uid');
        $statement->execute(['progress' => max(0, min(100, $percent)), 'uid' => $uid]);
    }

    /**
     * Cancels a job that has not started yet. Returns false if the job
     * does not exist or is no longer pending.
     */
    public function cancel(string $uid): bool
    {
        $statement = $this->pdo->prepare("UPDATE kontor_jobs SET status = 'cancelled' WHERE uid = :uid AND status = 'pending'");
        $statement->execute(['uid' => $uid]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $uid): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_jobs WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_jobs WHERE idempotency_key = :key');
        $statement->execute(['key' => $idempotencyKey]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $queue = null, ?string $status = null): array
    {
        $conditions = [];
        $params = [];

        if ($queue !== null) {
            $conditions[] = 'queue = :queue';
            $params['queue'] = $queue;
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT * FROM kontor_jobs';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(?string $queue = null, ?string $status = null, int $limit = 100): array
    {
        $conditions = [];
        $params = [];
        $limit = max(1, min($limit, 250));

        if ($queue !== null) {
            $conditions[] = 'queue = :queue';
            $params['queue'] = $queue;
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT * FROM kontor_jobs';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, int>
     */
    public function summaryCounts(): array
    {
        $counts = [
            'pending' => 0,
            'reserved' => 0,
            'completed' => 0,
            'dead' => 0,
            'cancelled' => 0,
        ];
        $statement = $this->pdo->query(
            'SELECT status, COUNT(*) AS job_count FROM kontor_jobs GROUP BY status'
        );

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['job_count'];
        }

        return $counts;
    }

    /**
     * @return string[]
     */
    public function queues(): array
    {
        $statement = $this->pdo->query('SELECT DISTINCT queue FROM kontor_jobs ORDER BY queue');

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return int count of jobs in the 'dead' state, for the health check
     */
    public function deadLetterCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_jobs WHERE status = 'dead'")->fetchColumn();
    }

    /**
     * Jobs reserved longer than $thresholdSeconds ago and never completed
     * — almost always a worker that crashed mid-job (health check signal).
     */
    public function stuckReservedCount(int $thresholdSeconds): int
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$thresholdSeconds} seconds");

        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM kontor_jobs WHERE status = 'reserved' AND started_at <= :cutoff"
        );
        $statement->execute(['cutoff' => $this->format($cutoff)]);

        return (int) $statement->fetchColumn();
    }

    private function format(\DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.u');
    }
}
