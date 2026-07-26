<?php

declare(strict_types=1);

namespace Kontor\Inventory\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Domain\InventoryMovement;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * kontor.md#16.3. An append-only ledger — no update()/archive(), only
 * insert and read.
 */
final class MovementRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(InventoryMovement $movement): void
    {
        $organizationId = $this->organizations->internalIdOf($movement->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_inventory_movements
                (uid, organization_id, movement_type, item_uid, source_warehouse_uid, destination_warehouse_uid,
                 quantity, unit_code, reference_type, reference_uid, reason, status, occurred_at, created_at,
                 created_by, idempotency_key, metadata_json)
             VALUES
                (:uid, :organization_id, :movement_type, :item_uid, :source_warehouse_uid, :destination_warehouse_uid,
                 :quantity, :unit_code, :reference_type, :reference_uid, :reason, :status, :occurred_at, :created_at,
                 :created_by, :idempotency_key, :metadata_json)'
        );

        $statement->execute([
            'uid' => $movement->uid->toString(),
            'organization_id' => $organizationId,
            'movement_type' => $movement->movementType,
            'item_uid' => $movement->itemUid,
            'source_warehouse_uid' => $movement->sourceWarehouseUid,
            'destination_warehouse_uid' => $movement->destinationWarehouseUid,
            'quantity' => $movement->quantity,
            'unit_code' => $movement->unitCode,
            'reference_type' => $movement->referenceType,
            'reference_uid' => $movement->referenceUid,
            'reason' => $movement->reason,
            'status' => $movement->status,
            'occurred_at' => $movement->occurredAt->format('Y-m-d H:i:s.u'),
            'created_at' => $movement->createdAt->format('Y-m-d H:i:s.u'),
            'created_by' => $movement->createdBy,
            'idempotency_key' => $movement->idempotencyKey,
            'metadata_json' => $movement->metadata !== [] ? json_encode($movement->metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function find(string $uid): ?InventoryMovement
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_inventory_movements WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): InventoryMovement
    {
        return $this->find($uid) ?? throw new RuntimeException("Inventory movement \"{$uid}\" was not found.");
    }

    /**
     * The idempotency check kontor.md#20.9 expects — a movement request
     * carrying an idempotency_key that's already been recorded returns the
     * original result instead of creating a duplicate.
     */
    public function findByIdempotencyKey(string $organizationUid, string $idempotencyKey): ?InventoryMovement
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_inventory_movements WHERE organization_id = :organization_id AND idempotency_key = :idempotency_key'
        );
        $statement->execute(['organization_id' => $organizationId, 'idempotency_key' => $idempotencyKey]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return InventoryMovement[] newest first
     */
    public function forItem(string $organizationUid, string $itemUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_inventory_movements WHERE organization_id = :organization_id AND item_uid = :item_uid ORDER BY occurred_at DESC'
        );
        $statement->execute(['organization_id' => $organizationId, 'item_uid' => $itemUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return InventoryMovement[] newest first
     */
    public function recentForOrganization(string $organizationUid, int $limit = 100): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $limit = max(1, min(500, $limit));
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_inventory_movements
             WHERE organization_id = :organization_id
             ORDER BY occurred_at DESC
             LIMIT {$limit}"
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): InventoryMovement
    {
        return new InventoryMovement(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            movementType: $row['movement_type'],
            itemUid: $row['item_uid'],
            sourceWarehouseUid: $row['source_warehouse_uid'],
            destinationWarehouseUid: $row['destination_warehouse_uid'],
            quantity: (float) $row['quantity'],
            unitCode: $row['unit_code'],
            referenceType: $row['reference_type'],
            referenceUid: $row['reference_uid'],
            reason: $row['reason'],
            status: $row['status'],
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
            idempotencyKey: $row['idempotency_key'],
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
