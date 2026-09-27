<?php

declare(strict_types=1);

namespace Kontor\Projects\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\Project;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class ProjectRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Project
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_projects WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Project
    {
        return $this->find($id) ?? throw new RuntimeException("Project \"{$id}\" was not found.");
    }

    /**
     * @return Project[]
     */
    public function forOrganization(string $organizationUid, int $limit = 100): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.*
             FROM kontor_projects p
             INNER JOIN kontor_organizations o ON o.id = p.organization_id
             WHERE o.uid = :organization_uid AND p.archived_at IS NULL
             ORDER BY p.updated_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('organization_uid', $organizationUid);
        $statement->bindValue('limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Project) {
            throw new InvalidArgumentException('ProjectRepository::save() expects a Project.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_projects
                (uid, organization_id, code, name, customer_type, customer_uid, status, start_date, end_date,
                 default_hourly_rate_minor, currency_code, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :code, :name, :customer_type, :customer_uid, :status, :start_date, :end_date,
                 :default_hourly_rate_minor, :currency_code, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), customer_type = VALUES(customer_type), customer_uid = VALUES(customer_uid),
                status = VALUES(status), start_date = VALUES(start_date), end_date = VALUES(end_date),
                default_hourly_rate_minor = VALUES(default_hourly_rate_minor), currency_code = VALUES(currency_code),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'code' => $entity->code,
            'name' => $entity->name,
            'customer_type' => $entity->customerType,
            'customer_uid' => $entity->customerUid,
            'status' => $entity->status,
            'start_date' => $entity->startDate?->format('Y-m-d'),
            'end_date' => $entity->endDate?->format('Y-m-d'),
            'default_hourly_rate_minor' => $entity->defaultHourlyRateMinor,
            'currency_code' => $entity->currencyCode,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_projects SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_projects SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    private function hydrate(array $row): Project
    {
        return new Project(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            code: $row['code'],
            name: $row['name'],
            customerType: $row['customer_type'],
            customerUid: $row['customer_uid'],
            status: $row['status'],
            startDate: $row['start_date'] !== null ? new \DateTimeImmutable($row['start_date']) : null,
            endDate: $row['end_date'] !== null ? new \DateTimeImmutable($row['end_date']) : null,
            defaultHourlyRateMinor: $row['default_hourly_rate_minor'] !== null ? (int) $row['default_hourly_rate_minor'] : null,
            currencyCode: $row['currency_code'],
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
