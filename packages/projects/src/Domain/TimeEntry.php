<?php

declare(strict_types=1);

namespace Kontor\Projects\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * `endedAt === null` means a timer is currently running — the only state
 * this class enforces itself; stop() is where TimeTrackingService (which
 * needs to persist the result) actually closes it out, so stop() lives in
 * the service, not here. This class only knows how to compute the
 * duration once it has both ends.
 */
final class TimeEntry
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $projectUid,
        public readonly ?string $milestoneUid,
        public readonly int $userId,
        public ?string $description,
        public readonly \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $endedAt,
        public ?int $durationMinutes,
        public bool $billable,
        public ?int $hourlyRateMinor,
        public ?string $currencyCode,
        public ?string $invoiceLineUid,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function start(
        string $organizationId,
        string $projectUid,
        int $userId,
        ?string $description = null,
        ?string $milestoneUid = null,
        bool $billable = true,
        ?int $hourlyRateMinor = null,
        ?string $currencyCode = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            projectUid: $projectUid,
            milestoneUid: $milestoneUid,
            userId: $userId,
            description: $description,
            startedAt: $now,
            endedAt: null,
            durationMinutes: null,
            billable: $billable,
            hourlyRateMinor: $hourlyRateMinor,
            currencyCode: $currencyCode,
            invoiceLineUid: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function logManual(
        string $organizationId,
        string $projectUid,
        int $userId,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $endedAt,
        ?string $description = null,
        ?string $milestoneUid = null,
        bool $billable = true,
        ?int $hourlyRateMinor = null,
        ?string $currencyCode = null,
    ): self {
        $entry = new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            projectUid: $projectUid,
            milestoneUid: $milestoneUid,
            userId: $userId,
            description: $description,
            startedAt: $startedAt,
            endedAt: null,
            durationMinutes: null,
            billable: $billable,
            hourlyRateMinor: $hourlyRateMinor,
            currencyCode: $currencyCode,
            invoiceLineUid: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $entry->close($endedAt);

        return $entry;
    }

    public function isRunning(): bool
    {
        return $this->endedAt === null;
    }

    public function isInvoiced(): bool
    {
        return $this->invoiceLineUid !== null;
    }

    public function close(\DateTimeImmutable $endedAt): void
    {
        if ($endedAt < $this->startedAt) {
            throw new \InvalidArgumentException('A time entry cannot end before it starts.');
        }

        $this->endedAt = $endedAt;
        $this->durationMinutes = (int) round(($endedAt->getTimestamp() - $this->startedAt->getTimestamp()) / 60);
    }

    public function durationHours(): float
    {
        return $this->durationMinutes !== null ? $this->durationMinutes / 60 : 0.0;
    }
}
