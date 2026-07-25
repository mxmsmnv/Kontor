<?php

declare(strict_types=1);

namespace Kontor\Projects\Application;

use Kontor\Projects\Domain\TimeEntry;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;
use RuntimeException;

/**
 * The "time tracking" milestone: start()/stop() are a real timer —
 * ended_at stays NULL between the two calls — plus logManual() for
 * retroactively-entered durations.
 */
final class TimeTrackingService
{
    public function __construct(private readonly TimeEntryRepository $entries)
    {
    }

    public function start(
        string $organizationUid,
        string $projectUid,
        int $userId,
        ?string $description = null,
        ?string $milestoneUid = null,
        bool $billable = true,
        ?int $hourlyRateMinor = null,
        ?string $currencyCode = null,
    ): TimeEntry {
        $entry = TimeEntry::start($organizationUid, $projectUid, $userId, $description, $milestoneUid, $billable, $hourlyRateMinor, $currencyCode);
        $this->entries->save($entry);

        return $entry;
    }

    public function stop(string $timeEntryUid): TimeEntry
    {
        $entry = $this->entries->require($timeEntryUid);

        if (!$entry->isRunning()) {
            throw new RuntimeException("Time entry \"{$timeEntryUid}\" is not running.");
        }

        $entry->close(new \DateTimeImmutable());
        $this->entries->save($entry);

        return $entry;
    }

    public function logManual(
        string $organizationUid,
        string $projectUid,
        int $userId,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $endedAt,
        ?string $description = null,
        ?string $milestoneUid = null,
        bool $billable = true,
        ?int $hourlyRateMinor = null,
        ?string $currencyCode = null,
    ): TimeEntry {
        $entry = TimeEntry::logManual(
            $organizationUid, $projectUid, $userId, $startedAt, $endedAt, $description, $milestoneUid,
            $billable, $hourlyRateMinor, $currencyCode,
        );
        $this->entries->save($entry);

        return $entry;
    }
}
