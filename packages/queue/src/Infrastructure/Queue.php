<?php

declare(strict_types=1);

namespace Kontor\Queue\Infrastructure;

use Kontor\Queue\Infrastructure\Persistence\JobRepositoryInterface;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use Kontor\SDK\Events\KontorEvent;

/**
 * kontor.md#9.10. The public entry point components use to enqueue work;
 * KontorQueue's registered "queue" capability (kontor.json) resolves to
 * this class.
 */
final class Queue implements QueueInterface
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function dispatch(JobInterface $job, ?QueueOptions $options = null): string
    {
        return $this->enqueue($job, new \DateTimeImmutable(), $options);
    }

    public function later(
        \DateTimeImmutable $when,
        JobInterface $job,
        ?QueueOptions $options = null
    ): string {
        return $this->enqueue($job, $when, $options);
    }

    public function cancel(string $jobId): bool
    {
        $cancelled = $this->jobs->cancel($jobId);

        if ($cancelled) {
            $this->emit('queue.job.cancelled', $jobId);
        }

        return $cancelled;
    }

    private function enqueue(JobInterface $job, \DateTimeImmutable $availableAt, ?QueueOptions $options): string
    {
        $options ??= new QueueOptions();

        $uid = $this->jobs->enqueue(
            queue: $options->queue,
            jobType: $job->jobType(),
            payload: $job->payload(),
            priority: $options->priority,
            maxAttempts: $options->maxAttempts,
            availableAt: $availableAt,
            idempotencyKey: $options->idempotencyKey,
        );

        $this->emit('queue.job.dispatched', $uid, ['jobType' => $job->jobType(), 'queue' => $options->queue]);

        return $uid;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $eventName, string $jobUid, array $data = []): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: 'system',
            entityType: 'job',
            entityId: $jobUid,
            actorType: 'system',
            actorId: null,
            data: $data,
        ));
    }
}
