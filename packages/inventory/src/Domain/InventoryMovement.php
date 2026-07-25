<?php

declare(strict_types=1);

namespace Kontor\Inventory\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#16.3. An append-only ledger row — never edited after
 * creation, so this class has no setters beyond what its constructor
 * already fills in. `movementType` drives which of source/destination is
 * expected to be set (kontor.md diagram 17.3): 'receive' — destination
 * only; 'transfer' — both; 'adjust' — exactly one, destination meaning an
 * increase and source meaning a decrease; 'reserve'/'release' —
 * destination only (the warehouse the reservation applies to).
 */
final class InventoryMovement
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $movementType,
        public readonly string $itemUid,
        public readonly ?string $sourceWarehouseUid,
        public readonly ?string $destinationWarehouseUid,
        public readonly float $quantity,
        public readonly string $unitCode,
        public readonly ?string $referenceType,
        public readonly ?string $referenceUid,
        public readonly ?string $reason,
        public readonly string $status,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?int $createdBy,
        public readonly ?string $idempotencyKey,
        public readonly array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $movementType,
        string $itemUid,
        ?string $sourceWarehouseUid,
        ?string $destinationWarehouseUid,
        float $quantity,
        string $unitCode = 'pcs',
        ?string $referenceType = null,
        ?string $referenceUid = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        array $metadata = [],
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            movementType: $movementType,
            itemUid: $itemUid,
            sourceWarehouseUid: $sourceWarehouseUid,
            destinationWarehouseUid: $destinationWarehouseUid,
            quantity: $quantity,
            unitCode: $unitCode,
            referenceType: $referenceType,
            referenceUid: $referenceUid,
            reason: $reason,
            status: 'completed',
            occurredAt: $now,
            createdAt: $now,
            createdBy: $createdBy,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }
}
