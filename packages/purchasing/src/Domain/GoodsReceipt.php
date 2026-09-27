<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class GoodsReceipt
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $purchaseOrderUid,
        public readonly string $warehouseUid,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(string $organizationId, string $purchaseOrderUid, string $warehouseUid, ?int $createdBy = null): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            purchaseOrderUid: $purchaseOrderUid,
            warehouseUid: $warehouseUid,
            receivedAt: $now,
            createdAt: $now,
            createdBy: $createdBy,
        );
    }
}
