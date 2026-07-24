<?php

declare(strict_types=1);

namespace Kontor\Queue\Application;

use Kontor\Queue\Infrastructure\JobProgressReporter;
use Kontor\Queue\Infrastructure\Persistence\JobRepositoryInterface;
use Kontor\Queue\JobRegistry;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

/**
 * Reserves and executes due jobs (Substage 2.1 "CLI worker" milestone,
 * called from bin/queue). A failed job is retried with exponential
 * backoff up to max_attempts, then moved to the dead-letter state
 * (kontor.md section 31).
 */
final class QueueWorker
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly JobRegistry $registry,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly int $backoffBaseSeconds = 10,
        private readonly int $backoffMaxSeconds = 3600,
    ) {
    }

    /**
     * Reserves and executes exactly one due job from $queue.
     *
     * @return bool whether a job was found and processed
     */
    public function processNext(string $queue): bool
    {
        $job = $this->jobs->reserveNext($queue);

        if ($job === null) {
            return false;
        }

        $this->execute($job);

        return true;
    }

    /**
     * Blocking loop: keeps processing due jobs, sleeping between empty
     * polls. Pass $maxIterations (of empty polls) to make the loop
     * terminate — used by tests and by `bin/queue work --once`.
     */
    public function work(string $queue, int $emptySleepSeconds = 1, ?int $maxIterations = null): void
    {
        $emptyPolls = 0;

        while (true) {
            if ($this->processNext($queue)) {
                $emptyPolls = 0;

                continue;
            }

            $emptyPolls++;

            if ($maxIterations !== null && $emptyPolls >= $maxIterations) {
                return;
            }

            if ($emptySleepSeconds > 0) {
                sleep($emptySleepSeconds);
            }
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function execute(array $job): void
    {
        $uid = $job['uid'];
        $payload = $job['payload_json'] !== null
            ? json_decode($job['payload_json'], associative: true, flags: JSON_THROW_ON_ERROR)
            : [];

        $this->emit('queue.job.started', $job);

        try {
            $instance = $this->registry->make($job['job_type'], $payload);
            $instance->handle($payload, new JobProgressReporter($this->jobs, $uid));

            $this->jobs->markCompleted($uid);
            $this->emit('queue.job.completed', $job);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $e);
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleFailure(array $job, \Throwable $e): void
    {
        $uid = $job['uid'];
        $attempts = (int) $job['attempts'];
        $maxAttempts = (int) $job['max_attempts'];

        if ($attempts >= $maxAttempts) {
            $this->jobs->markDead($uid, $e->getMessage());
            $this->emit('queue.job.dead', $job, ['error' => $e->getMessage(), 'attempts' => $attempts]);

            return;
        }

        $delaySeconds = min($this->backoffMaxSeconds, $this->backoffBaseSeconds * (2 ** ($attempts - 1)));
        $nextAttemptAt = (new \DateTimeImmutable())->modify("+{$delaySeconds} seconds");

        $this->jobs->markForRetry($uid, $e->getMessage(), $nextAttemptAt);
        $this->emit('queue.job.retrying', $job, [
            'error' => $e->getMessage(),
            'attempts' => $attempts,
            'nextAttemptInSeconds' => $delaySeconds,
        ]);
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $extra
     */
    private function emit(string $eventName, array $job, array $extra = []): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: 'system',
            entityType: 'job',
            entityId: $job['uid'],
            actorType: 'system',
            actorId: null,
            data: ['jobType' => $job['job_type'], 'queue' => $job['queue'], ...$extra],
        ));
    }
}
