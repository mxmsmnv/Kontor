<?php

declare(strict_types=1);

namespace Kontor\Expenses\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class ExpenseCategory
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $code,
        public string $name,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(string $organizationId, string $code, string $name, ?int $createdBy = null): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            code: $code,
            name: $name,
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
