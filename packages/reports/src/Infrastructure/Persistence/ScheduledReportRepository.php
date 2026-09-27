<?php

declare(strict_types=1);

namespace Kontor\Reports\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Reports\Domain\ScheduledReport;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class ScheduledReportRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?ScheduledReport
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_scheduled_reports WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): ScheduledReport
    {
        return $this->find($id) ?? throw new RuntimeException("Scheduled report \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof ScheduledReport) {
            throw new InvalidArgumentException('ScheduledReportRepository::save() expects a ScheduledReport.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_scheduled_reports
                (uid, organization_id, provider_key, name, filters_json, group_by_json, format, recurrence_rule,
                 next_run_at, last_run_at, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :provider_key, :name, :filters_json, :group_by_json, :format, :recurrence_rule,
                 :next_run_at, :last_run_at, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), filters_json = VALUES(filters_json), group_by_json = VALUES(group_by_json),
                format = VALUES(format), next_run_at = VALUES(next_run_at), last_run_at = VALUES(last_run_at),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'provider_key' => $entity->providerKey,
            'name' => $entity->name,
            'filters_json' => $entity->filters !== [] ? json_encode($entity->filters, JSON_THROW_ON_ERROR) : null,
            'group_by_json' => $entity->groupBy !== [] ? json_encode($entity->groupBy, JSON_THROW_ON_ERROR) : null,
            'format' => $entity->format,
            'recurrence_rule' => $entity->recurrenceRule,
            'next_run_at' => $entity->nextRunAt->format('Y-m-d H:i:s.u'),
            'last_run_at' => $entity->lastRunAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_scheduled_reports SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_scheduled_reports SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return ScheduledReport[]
     */
    public function due(string $organizationUid, \DateTimeImmutable $asOf): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_scheduled_reports
             WHERE organization_id = :organization_id AND next_run_at <= :as_of AND archived_at IS NULL
             ORDER BY next_run_at ASC'
        );
        $statement->execute(['organization_id' => $organizationId, 'as_of' => $asOf->format('Y-m-d H:i:s.u')]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return ScheduledReport[]
     */
    public function dueAcrossOrganizations(\DateTimeImmutable $asOf): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_scheduled_reports
             WHERE next_run_at <= :as_of AND archived_at IS NULL
             ORDER BY next_run_at ASC'
        );
        $statement->execute(['as_of' => $asOf->format('Y-m-d H:i:s.u')]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return ScheduledReport[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_scheduled_reports
             WHERE organization_id = :organization_id AND archived_at IS NULL
             ORDER BY next_run_at ASC, name ASC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ScheduledReport
    {
        return new ScheduledReport(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            providerKey: $row['provider_key'],
            name: $row['name'],
            filters: $row['filters_json'] !== null ? json_decode($row['filters_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            groupBy: $row['group_by_json'] !== null ? json_decode($row['group_by_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            format: $row['format'],
            recurrenceRule: $row['recurrence_rule'],
            nextRunAt: new \DateTimeImmutable($row['next_run_at']),
            lastRunAt: $row['last_run_at'] !== null ? new \DateTimeImmutable($row['last_run_at']) : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
