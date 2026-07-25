<?php

declare(strict_types=1);

namespace Kontor\Workflow\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class WorkflowTransition
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public readonly string $actionKey,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?string $requiredPermission,
        public readonly bool $requiresApproval,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $definitionUid,
        string $actionKey,
        string $fromState,
        string $toState,
        ?string $requiredPermission = null,
        bool $requiresApproval = false,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            actionKey: $actionKey,
            fromState: $fromState,
            toState: $toState,
            requiredPermission: $requiredPermission,
            requiresApproval: $requiresApproval,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
