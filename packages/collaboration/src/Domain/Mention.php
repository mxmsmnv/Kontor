<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Mention
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $commentUid,
        public readonly int $mentionedUserId,
        public readonly \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readAt = null,
    ) {
    }

    public static function create(string $organizationId, string $commentUid, int $mentionedUserId): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            commentUid: $commentUid,
            mentionedUserId: $mentionedUserId,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
