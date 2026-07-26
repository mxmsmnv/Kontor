<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Core\Infrastructure\Persistence\RelationRepository;

/**
 * The "entity linking" milestone. Reuses `kontor/core`'s own
 * `kontor_relations` table (kontor.md#11.7) directly via
 * `RelationRepository` rather than a parallel linking table — the same
 * choice `kontor/tasks` and `kontor/entities` already made for their own
 * "relations" milestones.
 */
final class EntityLinkingService
{
    private const RELATION_TYPE = 'mail_link';

    public function __construct(
        private readonly RelationRepository $relations,
    ) {
    }

    public function link(string $organizationUid, string $messageUid, string $entityType, string $entityUid, ?int $createdBy = null): string
    {
        return $this->relations->create(
            organizationUid: $organizationUid,
            sourceType: 'mail_message',
            sourceUid: $messageUid,
            targetType: $entityType,
            targetUid: $entityUid,
            relationType: self::RELATION_TYPE,
            createdBy: $createdBy,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linkedEntities(string $organizationUid, string $messageUid): array
    {
        return array_values(array_filter(
            $this->relations->relatedTo($organizationUid, 'mail_message', $messageUid),
            static fn (array $relation) => $relation['relationType'] === self::RELATION_TYPE,
        ));
    }
}
