<?php

declare(strict_types=1);

namespace Kontor\Workflow\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#18's "history" — one row per completed transition. Created
 * only by WorkflowEngine when a transition actually completes (never for
 * a pending approval request), so its own existence is proof the
 * transition happened.
 */
final class HistoryEntry
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public readonly string $entityType,
        public readonly string $entityUid,
        public readonly string $actionKey,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?int $actorUserId,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $definitionUid,
        string $entityType,
        string $entityUid,
        string $actionKey,
        string $fromState,
        string $toState,
        ?int $actorUserId = null,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            entityType: $entityType,
            entityUid: $entityUid,
            actionKey: $actionKey,
            fromState: $fromState,
            toState: $toState,
            actorUserId: $actorUserId,
            occurredAt: new \DateTimeImmutable(),
            metadata: $metadata,
        );
    }
}
