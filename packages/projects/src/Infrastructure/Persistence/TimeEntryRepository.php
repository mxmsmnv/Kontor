<?php

declare(strict_types=1);

namespace Kontor\Projects\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\TimeEntry;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class TimeEntryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(TimeEntry $entry): void
    {
        $organizationId = $this->organizations->internalIdOf($entry->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_project_time_entries
                (uid, organization_id, project_uid, milestone_uid, user_id, description, started_at, ended_at,
                 duration_minutes, billable, hourly_rate_minor, currency_code, invoice_line_uid, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :project_uid, :milestone_uid, :user_id, :description, :started_at, :ended_at,
                 :duration_minutes, :billable, :hourly_rate_minor, :currency_code, :invoice_line_uid, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                description = VALUES(description), ended_at = VALUES(ended_at), duration_minutes = VALUES(duration_minutes),
                billable = VALUES(billable), hourly_rate_minor = VALUES(hourly_rate_minor),
                currency_code = VALUES(currency_code), invoice_line_uid = VALUES(invoice_line_uid), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $entry->uid->toString(),
            'organization_id' => $organizationId,
            'project_uid' => $entry->projectUid,
            'milestone_uid' => $entry->milestoneUid,
            'user_id' => $entry->userId,
            'description' => $entry->description,
            'started_at' => $entry->startedAt->format('Y-m-d H:i:s.u'),
            'ended_at' => $entry->endedAt?->format('Y-m-d H:i:s.u'),
            'duration_minutes' => $entry->durationMinutes,
            'billable' => $entry->billable ? 1 : 0,
            'hourly_rate_minor' => $entry->hourlyRateMinor,
            'currency_code' => $entry->currencyCode,
            'invoice_line_uid' => $entry->invoiceLineUid,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entry->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?TimeEntry
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_time_entries WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): TimeEntry
    {
        return $this->find($uid) ?? throw new RuntimeException("Time entry \"{$uid}\" was not found.");
    }

    /**
     * @return TimeEntry[]
     */
    public function forProject(string $projectUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_time_entries WHERE project_uid = :project_uid ORDER BY started_at ASC');
        $statement->execute(['project_uid' => $projectUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Closed, billable, not-yet-invoiced entries — what
     * ProjectInvoicingService pulls into a new invoice.
     *
     * @return TimeEntry[]
     */
    public function uninvoicedBillableFor(string $projectUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_project_time_entries
             WHERE project_uid = :project_uid AND billable = 1 AND invoice_line_uid IS NULL AND ended_at IS NOT NULL
             ORDER BY started_at ASC'
        );
        $statement->execute(['project_uid' => $projectUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): TimeEntry
    {
        return new TimeEntry(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            projectUid: $row['project_uid'],
            milestoneUid: $row['milestone_uid'],
            userId: (int) $row['user_id'],
            description: $row['description'],
            startedAt: new \DateTimeImmutable($row['started_at']),
            endedAt: $row['ended_at'] !== null ? new \DateTimeImmutable($row['ended_at']) : null,
            durationMinutes: $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null,
            billable: (bool) $row['billable'],
            hourlyRateMinor: $row['hourly_rate_minor'] !== null ? (int) $row['hourly_rate_minor'] : null,
            currencyCode: $row['currency_code'],
            invoiceLineUid: $row['invoice_line_uid'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
