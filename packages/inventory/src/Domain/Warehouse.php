<?php

declare(strict_types=1);

namespace Kontor\Inventory\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#16.1
 */
final class Warehouse
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $code,
        public string $name,
        public ?string $addressUid,
        public ?int $managerUserId,
        public string $status,
        public array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $code,
        string $name,
        ?string $addressUid = null,
        ?int $managerUserId = null,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            code: $code,
            name: $name,
            addressUid: $addressUid,
            managerUserId: $managerUserId,
            status: 'active',
            metadata: $metadata,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
