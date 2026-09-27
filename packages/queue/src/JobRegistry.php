<?php

declare(strict_types=1);

namespace Kontor\Queue;

use Kontor\SDK\Contracts\JobInterface;
use RuntimeException;

/**
 * Maps a job_type string to a factory that rebuilds a JobInterface from a
 * stored payload. Needed because the worker that reserves a job is often a
 * different PHP process than the one that dispatched it — only the
 * job_type and payload survive in kontor_jobs (kontor.md#11.5).
 */
final class JobRegistry
{
    /**
     * @var array<string, callable(array<string, mixed>): JobInterface>
     */
    private array $factories = [];

    /**
     * @param callable(array<string, mixed>): JobInterface $factory
     */
    public function register(string $jobType, callable $factory): void
    {
        $this->factories[$jobType] = $factory;
    }

    public function has(string $jobType): bool
    {
        return isset($this->factories[$jobType]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function make(string $jobType, array $payload): JobInterface
    {
        if (!$this->has($jobType)) {
            throw new RuntimeException("No job factory is registered for job type \"{$jobType}\".");
        }

        return ($this->factories[$jobType])($payload);
    }
}
