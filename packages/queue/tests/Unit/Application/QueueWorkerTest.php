<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Unit\Application;

use Kontor\Queue\Application\QueueWorker;
use Kontor\Queue\Infrastructure\Queue;
use Kontor\Queue\JobRegistry;
use Kontor\Queue\Tests\Support\FakeJob;
use Kontor\Queue\Tests\Support\InMemoryJobRepository;
use Kontor\Queue\Tests\Support\RecordingEventDispatcher;
use Kontor\SDK\DTO\QueueOptions;
use PHPUnit\Framework\TestCase;

final class QueueWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        FakeJob::reset();
    }

    private function registry(): JobRegistry
    {
        $registry = new JobRegistry();
        $registry->register('fake.job', static fn (array $payload): FakeJob => new FakeJob($payload, throws: (bool) ($payload['throws'] ?? false)));

        return $registry;
    }

    public function test_process_next_returns_false_when_the_queue_is_empty(): void
    {
        $worker = new QueueWorker(new InMemoryJobRepository(), $this->registry());

        $this->assertFalse($worker->processNext('default'));
    }

    public function test_a_successful_job_is_marked_completed_and_reports_progress(): void
    {
        $jobs = new InMemoryJobRepository();
        $events = new RecordingEventDispatcher();
        $queue = new Queue($jobs, $events);
        $worker = new QueueWorker($jobs, $this->registry(), $events);

        $uid = $queue->dispatch(new FakeJob(['n' => 1]));
        $processed = $worker->processNext('default');

        $this->assertTrue($processed);
        $row = $jobs->find($uid);
        $this->assertSame('completed', $row['status']);
        $this->assertSame(100, $row['progress']);
        $this->assertSame([50, 100], FakeJob::$reportedProgress);
        $this->assertCount(1, $events->eventsNamed('queue.job.completed'));
    }

    public function test_a_failing_job_is_retried_with_backoff_when_attempts_remain(): void
    {
        $jobs = new InMemoryJobRepository();
        $events = new RecordingEventDispatcher();
        $queue = new Queue($jobs, $events);
        $worker = new QueueWorker($jobs, $this->registry(), $events, backoffBaseSeconds: 10);

        $uid = $queue->dispatch(new FakeJob(['throws' => true]), new QueueOptions(maxAttempts: 3));
        $worker->processNext('default');

        $row = $jobs->find($uid);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(1, $row['attempts']);
        $this->assertStringContainsString('simulated job failure', $row['error_message']);
        $this->assertGreaterThan((new \DateTimeImmutable())->modify('+5 seconds')->format('Y-m-d H:i:s.u'), $row['available_at']);
        $this->assertCount(1, $events->eventsNamed('queue.job.retrying'));

        // it must not be reservable again until available_at
        $this->assertNull($jobs->reserveNext('default'));
    }

    public function test_a_failing_job_moves_to_dead_letter_after_exhausting_attempts(): void
    {
        $jobs = new InMemoryJobRepository();
        $events = new RecordingEventDispatcher();
        $queue = new Queue($jobs, $events);
        $worker = new QueueWorker($jobs, $this->registry(), $events);

        $uid = $queue->dispatch(new FakeJob(['throws' => true]), new QueueOptions(maxAttempts: 1));
        $worker->processNext('default');

        $row = $jobs->find($uid);
        $this->assertSame('dead', $row['status']);
        $this->assertNotNull($row['failed_at']);
        $this->assertCount(1, $events->eventsNamed('queue.job.dead'));
        $this->assertSame(1, $jobs->deadLetterCount());
    }

    public function test_work_loop_processes_available_jobs_then_stops_after_empty_polls(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);
        $worker = new QueueWorker($jobs, $this->registry());

        $queue->dispatch(new FakeJob());
        $queue->dispatch(new FakeJob());

        $worker->work('default', emptySleepSeconds: 0, maxIterations: 2);

        $this->assertCount(2, $jobs->all('default', 'completed'));
    }
}
