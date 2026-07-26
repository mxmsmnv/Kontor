<?php

declare(strict_types=1);

namespace Kontor\Portal\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class PortalAccount
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $contactUid,
        public string $email,
        public string $passwordHash,
        public string $status,
        public ?\DateTimeImmutable $lastLoginAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $contactUid,
        string $email,
        string $passwordHash,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            contactUid: $contactUid,
            email: $email,
            passwordHash: $passwordHash,
            status: 'active',
            lastLoginAt: null,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastLoginAt;
    }

    public function disable(): void
    {
        $this->status = 'disabled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function enable(): void
    {
        $this->status = 'active';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
