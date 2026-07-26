<?php

declare(strict_types=1);

namespace Kontor\Workflow\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Workflow\Domain\WorkflowInstance;
use RuntimeException;

final class InstanceRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(WorkflowInstance $instance): void
    {
        $organizationId = $this->organizations->internalIdOf($instance->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_workflow_instances
                (uid, organization_id, definition_uid, entity_type, entity_uid, current_state, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :definition_uid, :entity_type, :entity_uid, :current_state, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE current_state = VALUES(current_state), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $instance->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $instance->definitionUid,
            'entity_type' => $instance->entityType,
            'entity_uid' => $instance->entityUid,
            'current_state' => $instance->currentState,
            'created_at' => $instance->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $instance->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?WorkflowInstance
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_instances WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): WorkflowInstance
    {
        return $this->find($uid) ?? throw new RuntimeException("Workflow instance \"{$uid}\" was not found.");
    }

    public function findForEntity(string $organizationUid, string $entityType, string $entityUid): ?WorkflowInstance
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_workflow_instances WHERE organization_id = :organization_id AND entity_type = :entity_type AND entity_uid = :entity_uid'
        );
        $statement->execute(['organization_id' => $organizationId, 'entity_type' => $entityType, 'entity_uid' => $entityUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function requireForEntity(string $organizationUid, string $entityType, string $entityUid): WorkflowInstance
    {
        return $this->findForEntity($organizationUid, $entityType, $entityUid)
            ?? throw new RuntimeException("No workflow instance for {$entityType} \"{$entityUid}\".");
    }

    /**
     * @return WorkflowInstance[]
     */
    public function forDefinition(string $definitionUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_workflow_instances
             WHERE definition_uid = :definition_uid
             ORDER BY updated_at DESC'
        );
        $statement->execute(['definition_uid' => $definitionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): WorkflowInstance
    {
        return new WorkflowInstance(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            currentState: $row['current_state'],
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
