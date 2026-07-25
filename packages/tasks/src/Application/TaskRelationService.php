<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\Core\Infrastructure\Persistence\RelationRepository;

/**
 * The "entity relations" milestone: kontor/tasks is the first real
 * consumer of Kontor\Core\Infrastructure\Persistence\RelationRepository
 * (kontor_relations, kontor.md#11.7 — see that class's own doc comment).
 * A task is always the relation's source; linking it to an entity records
 * a plain 'directed' relation unless the caller asks for 'bidirectional'.
 */
final class TaskRelationService
{
    private const ENTITY_TYPE = 'task';

    public function __construct(private readonly RelationRepository $relations)
    {
    }

    public function linkToEntity(
        string $organizationUid,
        string $taskUid,
        string $entityType,
        string $entityUid,
        string $relationType = 'relates_to',
        string $direction = 'directed',
    ): string {
        return $this->relations->create(
            $organizationUid, self::ENTITY_TYPE, $taskUid, $entityType, $entityUid, $relationType, $direction,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function relatedEntities(string $organizationUid, string $taskUid): array
    {
        return $this->relations->relatedTo($organizationUid, self::ENTITY_TYPE, $taskUid);
    }

    /**
     * Every task uid linked to a given entity.
     *
     * @return string[]
     */
    public function tasksRelatedTo(string $organizationUid, string $entityType, string $entityUid): array
    {
        $relations = $this->relations->relatedTo($organizationUid, $entityType, $entityUid);

        return array_values(array_unique(array_map(
            static fn (array $relation): string => $relation['sourceType'] === self::ENTITY_TYPE ? $relation['sourceUid'] : $relation['targetUid'],
            array_filter($relations, static fn (array $relation): bool => self::ENTITY_TYPE === $relation['sourceType'] || self::ENTITY_TYPE === $relation['targetType']),
        )));
    }
}
