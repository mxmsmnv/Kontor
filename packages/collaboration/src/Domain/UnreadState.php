<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class UnreadState
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly int $userId,
        public readonly string $entityType,
        public readonly string $entityUid,
        public \DateTimeImmutable $lastReadAt,
    ) {
    }

    public static function create(string $organizationId, int $userId, string $entityType, string $entityUid, \DateTimeImmutable $lastReadAt): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            userId: $userId,
            entityType: $entityType,
            entityUid: $entityUid,
            lastReadAt: $lastReadAt,
        );
    }
}
