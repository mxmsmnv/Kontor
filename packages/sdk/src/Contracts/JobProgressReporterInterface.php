<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

/**
 * Lets a running job report its own completion percentage back to the
 * queue (kontor.md section 31 "progress"). Handed to JobInterface::handle()
 * by whatever QueueInterface implementation is executing the job.
 */
interface JobProgressReporterInterface
{
    /**
     * @param int $percent 0-100
     */
    public function report(int $percent): void;
}
