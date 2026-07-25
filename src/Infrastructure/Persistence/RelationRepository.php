<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Persistence;

use Kontor\SDK\ValueObjects\Uid;

/**
 * Generic CRUD + lookup for kontor_relations (kontor.md#11.7) — a table
 * created in Substage 1.2 with no service until kontor/tasks' "entity
 * relations" milestone (Substage 5.1) became its first real consumer, same
 * "gap found, filled where the first real consumer needs it" pattern as
 * ExtensionRepository/SequenceService/ReportProviderRegistry. Works with
 * plain arrays rather than a domain object — like ExtensionRepository,
 * this is cross-cutting infrastructure any component can point at any pair
 * of entities, not a bounded-context entity of its own.
 */
final class RelationRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @param 'directed'|'bidirectional' $direction
     * @param array<string, mixed> $metadata
     */
    public function create(
        string $organizationUid,
        string $sourceType,
        string $sourceUid,
        string $targetType,
        string $targetUid,
        string $relationType,
        string $direction = 'directed',
        array $metadata = [],
        ?int $createdBy = null,
    ): string {
        $uid = Uid::generate()->toString();
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_relations
                (uid, organization_id, source_type, source_uid, target_type, target_uid, relation_type, direction,
                 metadata_json, created_at, created_by)
             VALUES
                (:uid, :organization_id, :source_type, :source_uid, :target_type, :target_uid, :relation_type, :direction,
                 :metadata_json, :created_at, :created_by)'
        );
        $statement->execute([
            'uid' => $uid,
            'organization_id' => $organizationId,
            'source_type' => $sourceType,
            'source_uid' => $sourceUid,
            'target_type' => $targetType,
            'target_uid' => $targetUid,
            'relation_type' => $relationType,
            'direction' => $direction,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'created_by' => $createdBy,
        ]);

        return $uid;
    }

    /**
     * Every active relation touching this entity: always as the source,
     * plus as the target when the relation was recorded 'bidirectional' —
     * a 'directed' relation only shows up from its source's side.
     *
     * @return array<int, array<string, mixed>>
     */
    public function relatedTo(string $organizationUid, string $entityType, string $entityUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_relations
             WHERE organization_id = :organization_id AND archived_at IS NULL
                AND (
                    (source_type = :source_entity_type AND source_uid = :source_entity_uid)
                    OR (target_type = :target_entity_type AND target_uid = :target_entity_uid AND direction = 'bidirectional')
                )
             ORDER BY created_at DESC"
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'source_entity_type' => $entityType,
            'source_entity_uid' => $entityUid,
            'target_entity_type' => $entityType,
            'target_entity_uid' => $entityUid,
        ]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function archive(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_relations SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $uid]);
    }

    public function restore(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_relations SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'uid' => $row['uid'],
            'sourceType' => $row['source_type'],
            'sourceUid' => $row['source_uid'],
            'targetType' => $row['target_type'],
            'targetUid' => $row['target_uid'],
            'relationType' => $row['relation_type'],
            'direction' => $row['direction'],
            'metadata' => $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            'createdAt' => $row['created_at'],
        ];
    }
}
