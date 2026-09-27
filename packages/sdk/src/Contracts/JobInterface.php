<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

interface JobInterface
{
    public function jobType(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;

    public function handle(array $payload, JobProgressReporterInterface $progress): void;
}
