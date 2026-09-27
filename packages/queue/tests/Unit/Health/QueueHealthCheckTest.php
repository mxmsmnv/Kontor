<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Unit\Health;

use Kontor\Queue\Application\QueueWorker;
use Kontor\Queue\Health\QueueHealthCheck;
use Kontor\Queue\Infrastructure\Queue;
use Kontor\Queue\JobRegistry;
use Kontor\Queue\Tests\Support\FakeJob;
use Kontor\Queue\Tests\Support\InMemoryJobRepository;
use Kontor\SDK\DTO\QueueOptions;
use PHPUnit\Framework\TestCase;

final class QueueHealthCheckTest extends TestCase
{
    public function test_ok_when_queue_is_empty(): void
    {
        $result = (new QueueHealthCheck(new InMemoryJobRepository()))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_warning_when_dead_letter_jobs_exist(): void
    {
        $jobs = new InMemoryJobRepository();
        $registry = new JobRegistry();
        $registry->register('fake.job', static fn (array $payload): FakeJob => new FakeJob($payload, throws: true));

        $queue = new Queue($jobs);
        $worker = new QueueWorker($jobs, $registry);

        $queue->dispatch(new FakeJob(['throws' => true]), new QueueOptions(maxAttempts: 1));
        $worker->processNext('default');

        $result = (new QueueHealthCheck($jobs))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['deadLetterCount']);
    }

    public function test_critical_when_a_job_has_been_reserved_too_long(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);
        $queue->dispatch(new FakeJob());
        $jobs->reserveNext('default');

        $result = (new QueueHealthCheck($jobs, stuckThresholdSeconds: 0))->run();

        $this->assertSame('critical', $result->status);
        $this->assertSame(1, $result->details['stuckCount']);
    }
}
