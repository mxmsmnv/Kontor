<?php

declare(strict_types=1);

namespace Kontor\Reports\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "scheduled reports" milestone. `nextRunAt()`'s recurrence math is
 * the same small daily/weekly/monthly/yearly set as kontor/tasks' own
 * Task::nextOccurrenceDueAt() — duplicated rather than shared across a
 * cross-package dependency for four lines of interval strings.
 */
final class ScheduledReport
{
    private const RECURRENCE_INTERVALS = [
        'daily' => '+1 day',
        'weekly' => '+1 week',
        'monthly' => '+1 month',
        'yearly' => '+1 year',
    ];

    /**
     * @param array<string, mixed> $filters
     * @param string[] $groupBy
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $providerKey,
        public string $name,
        public array $filters,
        public array $groupBy,
        public string $format,
        public readonly string $recurrenceRule,
        public \DateTimeImmutable $nextRunAt,
        public ?\DateTimeImmutable $lastRunAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param string[] $groupBy
     */
    public static function create(
        string $organizationId,
        string $providerKey,
        string $name,
        string $recurrenceRule,
        array $filters = [],
        array $groupBy = [],
        string $format = 'csv',
        ?\DateTimeImmutable $firstRunAt = null,
        ?int $createdBy = null,
    ): self {
        if (!array_key_exists($recurrenceRule, self::RECURRENCE_INTERVALS)) {
            throw new \InvalidArgumentException("\"{$recurrenceRule}\" is not a supported recurrence rule.");
        }

        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            providerKey: $providerKey,
            name: $name,
            filters: $filters,
            groupBy: $groupBy,
            format: $format,
            recurrenceRule: $recurrenceRule,
            nextRunAt: $firstRunAt ?? $now,
            lastRunAt: null,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    /**
     * Advances nextRunAt by one recurrence interval from itself — always
     * from the schedule, not from "now", so a schedule that's fallen
     * behind catches up one interval at a time rather than snapping to
     * whatever moment run() happened to execute at.
     */
    public function advance(): void
    {
        $this->nextRunAt = $this->nextRunAt->modify(self::RECURRENCE_INTERVALS[$this->recurrenceRule]);
    }

    public function isDue(\DateTimeImmutable $asOf): bool
    {
        return $this->nextRunAt <= $asOf;
    }
}
