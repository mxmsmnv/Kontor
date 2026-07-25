<?php

declare(strict_types=1);

namespace Kontor\Automation\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class AutomationRule
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public readonly string $triggerEvent,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(string $organizationId, string $name, string $triggerEvent, ?int $createdBy = null): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            triggerEvent: $triggerEvent,
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
