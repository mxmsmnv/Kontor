<?php

declare(strict_types=1);

namespace Kontor\Workflow\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class WorkflowInstance
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public readonly string $entityType,
        public readonly string $entityUid,
        public string $currentState,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $organizationId, string $definitionUid, string $entityType, string $entityUid, string $initialState): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            entityType: $entityType,
            entityUid: $entityUid,
            currentState: $initialState,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
