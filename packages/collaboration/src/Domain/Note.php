<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Note
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $entityType,
        public readonly string $entityUid,
        public string $body,
        public readonly \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
        public ?int $updatedBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $entityType,
        string $entityUid,
        string $body,
        ?int $createdBy = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            entityType: $entityType,
            entityUid: $entityUid,
            body: $body,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
            createdBy: $createdBy,
            updatedBy: null,
        );
    }
}
