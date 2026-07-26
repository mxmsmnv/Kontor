<?php

declare(strict_types=1);

namespace Kontor\Demo\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class DemoScenario
{
    /**
     * @param array<string, string> $entities
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public string $currentState,
        public string $status,
        public ?string $workflowInstanceUid,
        public ?string $pendingApprovalUid,
        public array $entities,
        public ?string $lastError,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(string $organizationId, string $name, ?int $createdBy = null): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            currentState: 'intake',
            status: 'active',
            workflowInstanceUid: null,
            pendingApprovalUid: null,
            entities: [],
            lastError: null,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    /**
     * @param array<string, string> $entities
     */
    public function record(array $entities): void
    {
        foreach ($entities as $type => $uid) {
            if ($type === '' || $uid === '') {
                throw new \InvalidArgumentException('Demo entity keys and UIDs cannot be empty.');
            }

            $this->entities[$type] = $uid;
        }

        $this->updatedAt = new \DateTimeImmutable();
    }

    public function entity(string $type): ?string
    {
        return $this->entities[$type] ?? null;
    }

    public function markFailed(string $message): void
    {
        $this->status = 'failed';
        $this->lastError = $message;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isComplete(): bool
    {
        return $this->currentState === 'completed';
    }
}
