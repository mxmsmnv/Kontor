<?php

declare(strict_types=1);

namespace Kontor\Entities\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Domain\EntityDefinition;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class EntityDefinitionRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?EntityDefinition
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_definitions WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): EntityDefinition
    {
        return $this->find($id) ?? throw new RuntimeException("Entity definition \"{$id}\" was not found.");
    }

    public function findByKey(string $organizationUid, string $entityKey): ?EntityDefinition
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_definitions WHERE organization_id = :organization_id AND entity_key = :entity_key');
        $statement->execute(['organization_id' => $organizationId, 'entity_key' => $entityKey]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof EntityDefinition) {
            throw new InvalidArgumentException('EntityDefinitionRepository::save() expects an EntityDefinition.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_entity_definitions
                (uid, organization_id, entity_key, name, view_permission, edit_permission, api_exposed, status,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :entity_key, :name, :view_permission, :edit_permission, :api_exposed, :status,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), view_permission = VALUES(view_permission), edit_permission = VALUES(edit_permission),
                api_exposed = VALUES(api_exposed), status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'entity_key' => $entity->entityKey,
            'name' => $entity->name,
            'view_permission' => $entity->viewPermission,
            'edit_permission' => $entity->editPermission,
            'api_exposed' => $entity->apiExposed ? 1 : 0,
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_entity_definitions SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_entity_definitions SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return EntityDefinition[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_definitions WHERE organization_id = :organization_id ORDER BY name ASC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): EntityDefinition
    {
        return new EntityDefinition(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            entityKey: $row['entity_key'],
            name: $row['name'],
            viewPermission: $row['view_permission'],
            editPermission: $row['edit_permission'],
            apiExposed: (bool) $row['api_exposed'],
            status: $row['status'],
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
