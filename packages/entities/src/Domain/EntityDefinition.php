<?php

declare(strict_types=1);

namespace Kontor\Entities\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class EntityDefinition
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $entityKey,
        public string $name,
        public ?string $viewPermission,
        public ?string $editPermission,
        public bool $apiExposed,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $entityKey,
        string $name,
        ?string $viewPermission = null,
        ?string $editPermission = null,
        bool $apiExposed = false,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            entityKey: $entityKey,
            name: $name,
            viewPermission: $viewPermission,
            editPermission: $editPermission,
            apiExposed: $apiExposed,
            status: 'active',
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
