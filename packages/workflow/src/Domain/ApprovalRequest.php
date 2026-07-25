<?php

declare(strict_types=1);

namespace Kontor\Workflow\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class ApprovalRequest
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $instanceUid,
        public readonly string $actionKey,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?int $requestedBy,
        public string $status,
        public ?int $decidedBy,
        public ?\DateTimeImmutable $decidedAt,
        public ?string $rejectionReason,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $instanceUid,
        string $actionKey,
        string $fromState,
        string $toState,
        ?int $requestedBy = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            instanceUid: $instanceUid,
            actionKey: $actionKey,
            fromState: $fromState,
            toState: $toState,
            requestedBy: $requestedBy,
            status: 'pending',
            decidedBy: null,
            decidedAt: null,
            rejectionReason: null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
