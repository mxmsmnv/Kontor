<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Dashboard\Domain\Dashboard;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class DashboardRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Dashboard
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_dashboards WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Dashboard
    {
        return $this->find($id) ?? throw new RuntimeException("Dashboard \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Dashboard) {
            throw new InvalidArgumentException('DashboardRepository::save() expects a Dashboard.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_dashboards
                (uid, organization_id, scope, owner_user_id, role, name, is_default, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :scope, :owner_user_id, :role, :name, :is_default, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), is_default = VALUES(is_default), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'scope' => $entity->scope,
            'owner_user_id' => $entity->ownerUserId,
            'role' => $entity->role,
            'name' => $entity->name,
            'is_default' => $entity->isDefault ? 1 : 0,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_dashboards SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_dashboards SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Dashboard[]
     */
    public function forOwner(string $organizationUid, int $ownerUserId): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_dashboards
             WHERE organization_id = :organization_id AND scope = 'personal' AND owner_user_id = :owner_user_id
                AND archived_at IS NULL
             ORDER BY created_at ASC"
        );
        $statement->execute(['organization_id' => $organizationId, 'owner_user_id' => $ownerUserId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Dashboard[]
     */
    public function forRole(string $organizationUid, string $role): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_dashboards
             WHERE organization_id = :organization_id AND scope = 'role' AND role = :role AND archived_at IS NULL
             ORDER BY created_at ASC"
        );
        $statement->execute(['organization_id' => $organizationId, 'role' => $role]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function defaultForOwner(string $organizationUid, int $ownerUserId): ?Dashboard
    {
        foreach ($this->forOwner($organizationUid, $ownerUserId) as $dashboard) {
            if ($dashboard->isDefault) {
                return $dashboard;
            }
        }

        return null;
    }

    public function defaultForRole(string $organizationUid, string $role): ?Dashboard
    {
        foreach ($this->forRole($organizationUid, $role) as $dashboard) {
            if ($dashboard->isDefault) {
                return $dashboard;
            }
        }

        return null;
    }

    private function hydrate(array $row): Dashboard
    {
        return new Dashboard(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            scope: $row['scope'],
            ownerUserId: $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
            role: $row['role'],
            name: $row['name'],
            isDefault: (bool) $row['is_default'],
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
