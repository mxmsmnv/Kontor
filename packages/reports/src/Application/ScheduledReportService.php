<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Reports\Domain\ScheduledReport;
use Kontor\Reports\Infrastructure\Persistence\ScheduledReportRepository;
use Kontor\SDK\DTO\ReportQuery;

/**
 * The "scheduled reports" milestone. run() executes and exports the report
 * and advances the schedule. ScheduledReportDispatcher sends due schedules
 * through Queue; ScheduledReportJob stores the finished export in Files.
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
     * @return ScheduledReport[]
     */
    public function dueSchedulesAcrossOrganizations(?\DateTimeImmutable $asOf = null): array
    {
        return $this->schedules->dueAcrossOrganizations($asOf ?? new \DateTimeImmutable());
    }

    /**
     * Runs a schedule now, exports it to $exportDirectory, advances
     * next_run_at by one recurrence interval, and returns the exported
     * file's path.
     */
    public function run(string $scheduleUid, string $exportDirectory): string
    {
        $path = $this->export($scheduleUid, $exportDirectory);
        $this->completeRun($scheduleUid);

        return $path;
    }

    /**
     * Exports without advancing the schedule. Queue delivery uses this so a
     * failed Files upload remains retryable for the same recurrence slot.
     */
    public function export(string $scheduleUid, string $exportDirectory): string
    {
        $schedule = $this->schedules->require($scheduleUid);

        $result = $this->builder->run($schedule->providerKey, new ReportQuery($schedule->organizationId, $schedule->filters, $schedule->groupBy));
        $fields = array_keys($this->providers->get($schedule->providerKey)->schema()->fields);

        $path = rtrim($exportDirectory, '/') . '/' . $schedule->uid->toString() . '.' . strtolower($schedule->format);
        $this->exporter->export($result, $fields, $schedule->format, $path);

        return $path;
    }

    public function completeRun(
        string $scheduleUid,
        ?\DateTimeImmutable $completedAt = null,
    ): ScheduledReport {
        $schedule = $this->schedules->require($scheduleUid);
        $schedule->lastRunAt = $completedAt ?? new \DateTimeImmutable();
        $schedule->advance();
        $this->schedules->save($schedule);

        return $schedule;
    }
}
