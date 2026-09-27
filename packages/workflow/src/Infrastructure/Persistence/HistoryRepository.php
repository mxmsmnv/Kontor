<?php

declare(strict_types=1);

namespace Kontor\Workflow\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Workflow\Domain\HistoryEntry;

final class HistoryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(HistoryEntry $entry): void
    {
        $organizationId = $this->organizations->internalIdOf($entry->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_workflow_history
                (uid, organization_id, definition_uid, entity_type, entity_uid, action_key, from_state, to_state,
                 actor_user_id, occurred_at, metadata_json)
             VALUES
                (:uid, :organization_id, :definition_uid, :entity_type, :entity_uid, :action_key, :from_state, :to_state,
                 :actor_user_id, :occurred_at, :metadata_json)'
        );

        $statement->execute([
            'uid' => $entry->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $entry->definitionUid,
            'entity_type' => $entry->entityType,
            'entity_uid' => $entry->entityUid,
            'action_key' => $entry->actionKey,
            'from_state' => $entry->fromState,
            'to_state' => $entry->toState,
            'actor_user_id' => $entry->actorUserId,
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s.u'),
            'metadata_json' => $entry->metadata !== [] ? json_encode($entry->metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    /**
     * @return HistoryEntry[] oldest first
     */
    public function forEntity(string $entityType, string $entityUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_workflow_history WHERE entity_type = :entity_type AND entity_uid = :entity_uid ORDER BY occurred_at ASC'
        );
        $statement->execute(['entity_type' => $entityType, 'entity_uid' => $entityUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): HistoryEntry
    {
        return new HistoryEntry(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            actionKey: $row['action_key'],
            fromState: $row['from_state'],
            toState: $row['to_state'],
            actorUserId: $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
