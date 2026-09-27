<?php

declare(strict_types=1);

namespace Kontor\Payments\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.5. Status transitions live in PaymentWorkflowService, not
 * here — confirming needs SequenceService, same split as every other
 * numbered document in this monorepo.
 */
final class Payment
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $number,
        public string $payerType,
        public string $payerUid,
        public ?\DateTimeImmutable $paymentDate,
        public Money $amount,
        public string $method,
        public ?string $transactionReference,
        public string $status,
        public ?string $externalId,
        public array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $payerType,
        string $payerUid,
        Money $amount,
        string $method = 'other',
        ?\DateTimeImmutable $paymentDate = null,
        ?string $transactionReference = null,
        ?string $externalId = null,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            number: null,
            payerType: $payerType,
            payerUid: $payerUid,
            paymentDate: $paymentDate,
            amount: $amount,
            method: $method,
            transactionReference: $transactionReference,
            status: 'draft',
            externalId: $externalId,
            metadata: $metadata,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
