<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Reports\Domain\ScheduledReport;
use Kontor\Reports\Infrastructure\Persistence\ScheduledReportRepository;
use Kontor\SDK\DTO\ReportQuery;

/**
 * The "scheduled reports" milestone. run() actually executes and exports
 * the report and advances the schedule — but nothing dispatches it
 * automatically. There's no scheduler component yet (Stage 7), so
 * dueSchedules() is the query, and run() the method, a future cron/queue-
 * backed dispatcher would call — same deferred-integration pattern
 * kontor/tasks' reminders and kontor/invoices' sweepOverdue() already
 * established. Actually delivering the exported file (email, upload) is
 * further out of scope still — run() just produces it on disk.
 */
final class ScheduledReportService
{
    public function __construct(
        private readonly ScheduledReportRepository $schedules,
        private readonly ReportProviderRegistry $providers,
        private readonly ReportBuilderService $builder,
        private readonly ReportExportService $exporter,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param string[] $groupBy
     */
    public function schedule(
        string $organizationUid,
        string $providerKey,
        string $name,
        string $recurrenceRule,
        array $filters = [],
        array $groupBy = [],
        string $format = 'csv',
        ?\DateTimeImmutable $firstRunAt = null,
        ?int $createdBy = null,
    ): ScheduledReport {
        // Validates the provider exists and the filters/groupBy are ones
        // it actually declares, failing fast at schedule time rather than
        // only discovering a bad field the next time this runs — without
        // paying for a query execution the caller isn't ready to run yet.
        $this->builder->validateFor($providerKey, $filters, $groupBy);

        $schedule = ScheduledReport::create($organizationUid, $providerKey, $name, $recurrenceRule, $filters, $groupBy, $format, $firstRunAt, $createdBy);
        $this->schedules->save($schedule);

        return $schedule;
    }

    /**
     * @return ScheduledReport[]
     */
    public function dueSchedules(string $organizationUid, ?\DateTimeImmutable $asOf = null): array
    {
        return $this->schedules->due($organizationUid, $asOf ?? new \DateTimeImmutable());
    }

    /**
     * Runs a schedule now, exports it to $exportDirectory, advances
     * next_run_at by one recurrence interval, and returns the exported
     * file's path.
     */
    public function run(string $scheduleUid, string $exportDirectory): string
    {
        $schedule = $this->schedules->require($scheduleUid);

        $result = $this->builder->run($schedule->providerKey, new ReportQuery($schedule->organizationId, $schedule->filters, $schedule->groupBy));
        $fields = array_keys($this->providers->get($schedule->providerKey)->schema()->fields);

        $path = rtrim($exportDirectory, '/') . '/' . $schedule->uid->toString() . '.' . strtolower($schedule->format);
        $this->exporter->export($result, $fields, $schedule->format, $path);

        $schedule->lastRunAt = new \DateTimeImmutable();
        $schedule->advance();
        $this->schedules->save($schedule);

        return $path;
    }
}
