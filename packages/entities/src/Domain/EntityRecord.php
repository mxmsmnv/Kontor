<?php

declare(strict_types=1);

namespace Kontor\Entities\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class EntityRecord
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public array $data,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(string $organizationId, string $definitionUid, array $data, ?int $createdBy = null): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            data: $data,
            status: 'active',
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }
}
