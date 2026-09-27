<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\QueueOptions;

interface QueueInterface
{
    public function dispatch(JobInterface $job, ?QueueOptions $options = null): string;

    public function later(
        \DateTimeImmutable $when,
        JobInterface $job,
        ?QueueOptions $options = null
    ): string;

    public function cancel(string $jobId): bool;
}
