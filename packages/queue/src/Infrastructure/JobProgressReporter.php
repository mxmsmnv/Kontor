<?php

declare(strict_types=1);

namespace Kontor\Queue\Infrastructure;

use Kontor\Queue\Infrastructure\Persistence\JobRepositoryInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;

final class JobProgressReporter implements JobProgressReporterInterface
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly string $jobUid,
    ) {
    }

    public function report(int $percent): void
    {
        $this->jobs->updateProgress($this->jobUid, $percent);
    }
}
