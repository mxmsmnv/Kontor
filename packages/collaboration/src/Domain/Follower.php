<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Follower
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $entityType,
        public readonly string $entityUid,
        public readonly int $userId,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $organizationId, string $entityType, string $entityUid, int $userId): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            entityType: $entityType,
            entityUid: $entityUid,
            userId: $userId,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
