<?php

declare(strict_types=1);

namespace Kontor\Projects\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

final class BillableItem
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $projectUid,
        public readonly ?string $milestoneUid,
        public string $description,
        public float $quantity,
        public Money $unitPrice,
        public ?string $invoiceLineUid,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $projectUid,
        string $description,
        float $quantity,
        Money $unitPrice,
        ?string $milestoneUid = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            projectUid: $projectUid,
            milestoneUid: $milestoneUid,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            invoiceLineUid: null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function isInvoiced(): bool
    {
        return $this->invoiceLineUid !== null;
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
