<?php

declare(strict_types=1);

namespace Kontor\Projects\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class ProjectMilestone
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $projectUid,
        public string $name,
        public ?\DateTimeImmutable $dueDate,
        public string $status,
        public ?\DateTimeImmutable $completedAt,
        public int $sortOrder,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $organizationId, string $projectUid, string $name, ?\DateTimeImmutable $dueDate = null, int $sortOrder = 0): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            projectUid: $projectUid,
            name: $name,
            dueDate: $dueDate,
            status: 'pending',
            completedAt: null,
            sortOrder: $sortOrder,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
