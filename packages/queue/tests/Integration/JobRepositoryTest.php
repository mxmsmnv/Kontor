<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Integration;

use Kontor\Queue\Infrastructure\Persistence\JobRepository;

final class JobRepositoryTest extends DatabaseTestCase
{
    public function test_enqueue_then_find_round_trips(): void
    {
        $jobs = new JobRepository($this->pdo);

        $uid = $jobs->enqueue('default', 'send.email', ['to' => 'a@b.test'], 0, 3, new \DateTimeImmutable(), null);
        $row = $jobs->find($uid);

        $this->assertSame('send.email', $row['job_type']);
        $this->assertSame('{"to":"a@b.test"}', $row['payload_json']);
        $this->assertSame('pending', $row['status']);
    }

    public function test_reserve_next_respects_priority_over_fifo(): void
    {
        $jobs = new JobRepository($this->pdo);
        $now = new \DateTimeImmutable();

        $low = $jobs->enqueue('default', 'job.low', [], 0, 3, $now, null);
        $high = $jobs->enqueue('default', 'job.high', [], 10, 3, $now, null);

        $reserved = $jobs->reserveNext('default');

        $this->assertSame($high, $reserved['uid']);
    }

    public function test_reserve_next_ignores_jobs_not_yet_available(): void
    {
        $jobs = new JobRepository($this->pdo);
        $jobs->enqueue('default', 'job.future', [], 0, 3, (new \DateTimeImmutable())->modify('+1 hour'), null);

        $this->assertNull($jobs->reserveNext('default'));
    }

    public function test_reserve_next_skips_a_row_locked_by_another_connection(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uidA = $jobs->enqueue('default', 'job.a', [], 5, 3, new \DateTimeImmutable(), null);
        $jobs->enqueue('default', 'job.b', [], 1, 3, new \DateTimeImmutable(), null);

        // hold a row lock on the higher-priority job from a second connection,
        // without committing — simulates another worker mid-reservation
        $second = $this->secondConnection();
        $second->beginTransaction();
        $second->prepare('SELECT * FROM kontor_jobs WHERE uid = :uid FOR UPDATE')->execute(['uid' => $uidA]);

        try {
            $reserved = $jobs->reserveNext('default');

            // job.a is locked, so SKIP LOCKED must fall through to job.b
            $this->assertSame('job.b', $reserved['job_type']);
        } finally {
            $second->rollBack();
        }
    }

    public function test_idempotency_key_returns_the_same_uid_regardless_of_status(): void
    {
        $jobs = new JobRepository($this->pdo);

        $first = $jobs->enqueue('default', 'send.invoice', [], 0, 3, new \DateTimeImmutable(), 'inv-123');
        $jobs->markCompleted($first);

        $second = $jobs->enqueue('default', 'send.invoice', [], 0, 3, new \DateTimeImmutable(), 'inv-123');

        $this->assertSame($first, $second);
        $this->assertCount(1, $jobs->all('default'));
    }

    public function test_mark_completed_sets_progress_and_finished_at(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 3, new \DateTimeImmutable(), null);
        $jobs->reserveNext('default');

        $jobs->markCompleted($uid);

        $row = $jobs->find($uid);
        $this->assertSame('completed', $row['status']);
        $this->assertSame(100, (int) $row['progress']);
        $this->assertNotNull($row['finished_at']);
    }

    public function test_mark_for_retry_reopens_the_job_for_a_future_attempt(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 3, new \DateTimeImmutable(), null);
        $jobs->reserveNext('default');

        $future = (new \DateTimeImmutable())->modify('+1 hour');
        $jobs->markForRetry($uid, 'boom', $future);

        $row = $jobs->find($uid);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('boom', $row['error_message']);
        $this->assertNull($jobs->reserveNext('default'));
    }

    public function test_mark_dead_is_permanent_and_counted(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 1, new \DateTimeImmutable(), null);
        $jobs->reserveNext('default');

        $jobs->markDead($uid, 'boom');

        $this->assertSame(1, $jobs->deadLetterCount());
        $this->assertNull($jobs->reserveNext('default'));
    }

    public function test_update_progress_clamps_to_0_100(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 3, new \DateTimeImmutable(), null);

        $jobs->updateProgress($uid, 150);
        $this->assertSame(100, (int) $jobs->find($uid)['progress']);

        $jobs->updateProgress($uid, -20);
        $this->assertSame(0, (int) $jobs->find($uid)['progress']);
    }

    public function test_cancel_only_affects_pending_jobs(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 3, new \DateTimeImmutable(), null);

        $this->assertTrue($jobs->cancel($uid));
        $this->assertSame('cancelled', $jobs->find($uid)['status']);
        $this->assertFalse($jobs->cancel($uid));
    }

    public function test_stuck_reserved_count_finds_long_running_reservations(): void
    {
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'job.x', [], 0, 3, new \DateTimeImmutable(), null);
        $jobs->reserveNext('default');

        $this->assertSame(0, $jobs->stuckReservedCount(3600));
        $this->assertSame(1, $jobs->stuckReservedCount(0));
    }

    private function secondConnection(): \PDO
    {
        return new \PDO(
            getenv('KONTOR_TEST_DB_DSN'),
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
