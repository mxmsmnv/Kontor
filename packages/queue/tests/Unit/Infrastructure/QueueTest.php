<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Unit\Infrastructure;

use Kontor\Queue\Infrastructure\Queue;
use Kontor\Queue\Tests\Support\FakeJob;
use Kontor\Queue\Tests\Support\InMemoryJobRepository;
use Kontor\Queue\Tests\Support\RecordingEventDispatcher;
use Kontor\SDK\DTO\QueueOptions;
use PHPUnit\Framework\TestCase;

final class QueueTest extends TestCase
{
    public function test_dispatch_enqueues_immediately_available(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);

        $uid = $queue->dispatch(new FakeJob(['n' => 1]));

        $row = $jobs->find($uid);
        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('default', $row['queue']);
    }

    public function test_dispatch_respects_queue_options(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);

        $uid = $queue->dispatch(new FakeJob(), new QueueOptions(queue: 'reports', priority: 5, maxAttempts: 7));

        $row = $jobs->find($uid);
        $this->assertSame('reports', $row['queue']);
        $this->assertSame(5, $row['priority']);
        $this->assertSame(7, $row['max_attempts']);
    }

    public function test_later_sets_a_future_available_at(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);
        $future = (new \DateTimeImmutable())->modify('+1 hour');

        $uid = $queue->later($future, new FakeJob());

        $row = $jobs->find($uid);
        $this->assertNull($jobs->reserveNext('default'));
        $this->assertSame($future->format('Y-m-d H:i:s.u'), $row['available_at']);
    }

    public function test_cancel_only_succeeds_for_pending_jobs(): void
    {
        $jobs = new InMemoryJobRepository();
        $queue = new Queue($jobs);

        $uid = $queue->dispatch(new FakeJob());
        $this->assertTrue($queue->cancel($uid));
        $this->assertFalse($queue->cancel($uid));
    }

    public function test_dispatch_emits_a_dispatched_event(): void
    {
        $jobs = new InMemoryJobRepository();
        $events = new RecordingEventDispatcher();
        $queue = new Queue($jobs, $events);

        $queue->dispatch(new FakeJob());

        $this->assertCount(1, $events->eventsNamed('queue.job.dispatched'));
    }

    public function test_cancel_emits_a_cancelled_event_only_on_success(): void
    {
        $jobs = new InMemoryJobRepository();
        $events = new RecordingEventDispatcher();
        $queue = new Queue($jobs, $events);

        $uid = $queue->dispatch(new FakeJob());
        $queue->cancel($uid);
        $queue->cancel($uid);

        $this->assertCount(1, $events->eventsNamed('queue.job.cancelled'));
    }
}
