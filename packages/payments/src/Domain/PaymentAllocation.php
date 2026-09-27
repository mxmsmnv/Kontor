<?php

declare(strict_types=1);

namespace Kontor\Payments\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.6 — the "allocations" / "partial payments" milestone: one
 * payment can fund multiple documents (or the same document across
 * multiple payments) via any number of these rows, each for a slice of
 * the payment's amount.
 */
final class PaymentAllocation
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $paymentUid,
        public readonly string $documentType,
        public readonly string $documentUid,
        public readonly Money $amount,
        public readonly \DateTimeImmutable $allocatedAt,
        public ?\DateTimeImmutable $reversedAt = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $paymentUid,
        string $documentType,
        string $documentUid,
        Money $amount,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            paymentUid: $paymentUid,
            documentType: $documentType,
            documentUid: $documentUid,
            amount: $amount,
            allocatedAt: new \DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    public function isReversed(): bool
    {
        return $this->reversedAt !== null;
    }
}
