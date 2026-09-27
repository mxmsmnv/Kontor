<?php

declare(strict_types=1);

namespace Kontor\Entities\Application;

use Kontor\Core\Infrastructure\Persistence\RelationRepository;

/**
 * The "relations" milestone: reuses Kontor\Core\Infrastructure\
 * Persistence\RelationRepository (kontor_relations, kontor.md#11.7)
 * directly rather than a parallel custom-entities-only relations table —
 * the same reuse kontor/tasks' own TaskRelationService already
 * established for that repository. A custom entity record's own
 * "entity_type" for relation purposes is its definition's `entity_key`.
 */
final class EntityRelationService
{
    public function __construct(private readonly RelationRepository $relations)
    {
    }

    public function linkRecords(
        string $organizationUid,
        string $sourceEntityKey,
        string $sourceRecordUid,
        string $targetType,
        string $targetUid,
        string $relationType = 'relates_to',
        string $direction = 'directed',
    ): string {
        return $this->relations->create($organizationUid, $sourceEntityKey, $sourceRecordUid, $targetType, $targetUid, $relationType, $direction);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function relatedTo(string $organizationUid, string $entityKey, string $recordUid): array
    {
        return $this->relations->relatedTo($organizationUid, $entityKey, $recordUid);
    }
}
