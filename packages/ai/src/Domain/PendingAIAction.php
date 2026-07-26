<?php

declare(strict_types=1);

namespace Kontor\AI\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "approval workflow" milestone: a critical AI action's output,
 * held for a human decision before anything happens with it.
 */
final class PendingAIAction
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $capability,
        public readonly array $input,
        public readonly array $output,
        public string $status,
        public readonly ?int $requestedBy,
        public ?int $decidedBy,
        public readonly \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $decidedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    public static function create(
        string $organizationId,
        string $capability,
        array $input,
        array $output,
        ?int $requestedBy = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            capability: $capability,
            input: $input,
            output: $output,
            status: 'pending',
            requestedBy: $requestedBy,
            decidedBy: null,
            createdAt: new \DateTimeImmutable(),
            decidedAt: null,
        );
    }

    public function approve(?int $decidedBy): void
    {
        $this->status = 'approved';
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();
    }

    public function reject(?int $decidedBy): void
    {
        $this->status = 'rejected';
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
