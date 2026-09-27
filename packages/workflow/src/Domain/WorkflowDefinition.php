<?php

declare(strict_types=1);

namespace Kontor\Workflow\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class WorkflowDefinition
{
    /**
     * @param string[] $states
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $workflowKey,
        public readonly string $entityType,
        public string $name,
        public readonly string $initialState,
        public readonly array $states,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param string[] $states
     */
    public static function create(
        string $organizationId,
        string $workflowKey,
        string $entityType,
        string $name,
        string $initialState,
        array $states,
        ?int $createdBy = null,
    ): self {
        if (!in_array($initialState, $states, true)) {
            throw new \InvalidArgumentException("Initial state \"{$initialState}\" must be one of the workflow's declared states.");
        }

        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            workflowKey: $workflowKey,
            entityType: $entityType,
            name: $name,
            initialState: $initialState,
            states: $states,
            status: 'active',
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function hasState(string $state): bool
    {
        return in_array($state, $this->states, true);
    }
}
