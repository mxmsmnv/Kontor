<?php

declare(strict_types=1);

namespace Kontor\Queue\Infrastructure;

use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use RuntimeException;

/**
 * A dispatch-only JobInterface wrapper for CLI/ad-hoc dispatch of a
 * job_type string and payload array (bin/queue's `job:dispatch`). It must
 * never be executed directly — the worker always reconstructs the *real*
 * job class from job_type via JobRegistry, so this only ever needs to
 * carry data from dispatch() into kontor_jobs.
 */
final class GenericJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $type,
        private readonly array $payload,
    ) {
    }

    public function jobType(): string
    {
        return $this->type;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        throw new RuntimeException(
            'GenericJob is dispatch-only. The worker must reconstruct the real job class '.
            "for \"{$this->type}\" via JobRegistry, not execute this wrapper."
        );
    }
}
