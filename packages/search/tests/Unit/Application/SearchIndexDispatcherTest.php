<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Application;

use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use Kontor\Search\Application\SearchIndexDispatcher;
use Kontor\Search\Infrastructure\Queue\SearchIndexJob;
use PHPUnit\Framework\TestCase;

final class SearchIndexDispatcherTest extends TestCase
{
    public function test_reindex_dispatches_a_search_index_job_with_the_index_action(): void
    {
        $queue = new RecordingQueue();
        $dispatcher = new SearchIndexDispatcher($queue);

        $dispatcher->reindex('contact', 'ct_01', ['name' => 'Acme']);

        $this->assertInstanceOf(SearchIndexJob::class, $queue->dispatched);
        $this->assertSame(
            ['action' => 'index', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => ['name' => 'Acme']],
            $queue->dispatched->payload()
        );
    }

    public function test_remove_from_index_dispatches_a_search_index_job_with_the_remove_action(): void
    {
        $queue = new RecordingQueue();
        $dispatcher = new SearchIndexDispatcher($queue);

        $dispatcher->removeFromIndex('contact', 'ct_01');

        $this->assertSame('remove', $queue->dispatched->payload()['action']);
    }
}

final class RecordingQueue implements QueueInterface
{
    public ?JobInterface $dispatched = null;

    public function dispatch(JobInterface $job, ?QueueOptions $options = null): string
    {
        $this->dispatched = $job;

        return 'job_01';
    }

    public function later(\DateTimeImmutable $when, JobInterface $job, ?QueueOptions $options = null): string
    {
        $this->dispatched = $job;

        return 'job_01';
    }

    public function cancel(string $jobId): bool
    {
        return true;
    }
}
