<?php

declare(strict_types=1);

namespace Kontor\Workflow\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Workflow\Domain\WorkflowDefinition;
use InvalidArgumentException;
use RuntimeException;

final class DefinitionRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?WorkflowDefinition
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_definitions WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): WorkflowDefinition
    {
        return $this->find($id) ?? throw new RuntimeException("Workflow definition \"{$id}\" was not found.");
    }

    public function findByKey(string $organizationUid, string $workflowKey): ?WorkflowDefinition
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_definitions WHERE organization_id = :organization_id AND workflow_key = :workflow_key');
        $statement->execute(['organization_id' => $organizationId, 'workflow_key' => $workflowKey]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof WorkflowDefinition) {
            throw new InvalidArgumentException('DefinitionRepository::save() expects a WorkflowDefinition.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_workflow_definitions
                (uid, organization_id, workflow_key, entity_type, name, initial_state, states_json, status,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :workflow_key, :entity_type, :name, :initial_state, :states_json, :status,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'workflow_key' => $entity->workflowKey,
            'entity_type' => $entity->entityType,
            'name' => $entity->name,
            'initial_state' => $entity->initialState,
            'states_json' => json_encode($entity->states, JSON_THROW_ON_ERROR),
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_workflow_definitions SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_workflow_definitions SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    private function hydrate(array $row): WorkflowDefinition
    {
        return new WorkflowDefinition(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            workflowKey: $row['workflow_key'],
            entityType: $row['entity_type'],
            name: $row['name'],
            initialState: $row['initial_state'],
            states: json_decode($row['states_json'], associative: true, flags: JSON_THROW_ON_ERROR),
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
