<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class GoodsReceiptLine
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $receiptUid,
        public readonly string $poLineUid,
        public readonly string $itemUid,
        public readonly float $quantityReceived,
        public readonly string $unitCode,
    ) {
    }

    public static function create(
        string $organizationId,
        string $receiptUid,
        string $poLineUid,
        string $itemUid,
        float $quantityReceived,
        string $unitCode = 'pcs',
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            receiptUid: $receiptUid,
            poLineUid: $poLineUid,
            itemUid: $itemUid,
            quantityReceived: $quantityReceived,
            unitCode: $unitCode,
        );
    }
}
