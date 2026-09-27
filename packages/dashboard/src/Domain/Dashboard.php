<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md Substage 5.3 "personal dashboards"/"role dashboards" — scope
 * is 'personal' (owner_user_id set) or 'role' (role set); "only one
 * default per scope" is enforced by DashboardService, not here.
 */
final class Dashboard
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $scope,
        public readonly ?int $ownerUserId,
        public readonly ?string $role,
        public string $name,
        public bool $isDefault,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function personal(string $organizationId, int $ownerUserId, string $name, bool $isDefault = false, ?int $createdBy = null): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            scope: 'personal',
            ownerUserId: $ownerUserId,
            role: null,
            name: $name,
            isDefault: $isDefault,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            createdBy: $createdBy,
        );
    }

    public static function forRole(string $organizationId, string $role, string $name, bool $isDefault = false, ?int $createdBy = null): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            scope: 'role',
            ownerUserId: null,
            role: $role,
            name: $name,
            isDefault: $isDefault,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            createdBy: $createdBy,
        );
    }

    public function isPersonal(): bool
    {
        return $this->scope === 'personal';
    }
}
