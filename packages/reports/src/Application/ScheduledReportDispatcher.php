<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use Kontor\Reports\Infrastructure\Queue\ScheduledReportJob;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;

final class ScheduledReportDispatcher
{
    public function __construct(
        private readonly ScheduledReportService $reports,
        private readonly QueueInterface $queue,
    ) {
    }

    /**
     * Queues every due schedule once for its current recurrence slot.
     *
     * @return array<string, string> schedule uid => queue job uid
     */
    public function dispatchDue(
        ?string $organizationUid = null,
        ?\DateTimeImmutable $asOf = null,
    ): array {
        $due = $organizationUid !== null
            ? $this->reports->dueSchedules($organizationUid, $asOf)
            : $this->reports->dueSchedulesAcrossOrganizations($asOf);
        $jobs = [];

        foreach ($due as $schedule) {
            $slot = $schedule->nextRunAt->format('YmdHis.u');
            $jobs[$schedule->uid->toString()] = $this->queue->dispatch(
                ScheduledReportJob::forSchedule($schedule),
                new QueueOptions(
                    queue: 'reports',
                    priority: 30,
                    maxAttempts: 5,
                    idempotencyKey: 'scheduled-report:'.$schedule->uid->toString().':'.$slot,
                ),
            );
        }

        return $jobs;
    }
}
