<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Purchasing\Domain\GoodsReceipt;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * Append-only, like kontor/inventory's own movement ledger — a receipt is
 * never edited after the fact.
 */
final class GoodsReceiptRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(GoodsReceipt $receipt): void
    {
        $organizationId = $this->organizations->internalIdOf($receipt->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_purchasing_receipts
                (uid, organization_id, purchase_order_uid, warehouse_uid, received_at, created_at, created_by)
             VALUES
                (:uid, :organization_id, :purchase_order_uid, :warehouse_uid, :received_at, :created_at, :created_by)'
        );

        $statement->execute([
            'uid' => $receipt->uid->toString(),
            'organization_id' => $organizationId,
            'purchase_order_uid' => $receipt->purchaseOrderUid,
            'warehouse_uid' => $receipt->warehouseUid,
            'received_at' => $receipt->receivedAt->format('Y-m-d H:i:s.u'),
            'created_at' => $receipt->createdAt->format('Y-m-d H:i:s.u'),
            'created_by' => $receipt->createdBy,
        ]);
    }

    public function find(string $uid): ?GoodsReceipt
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_purchasing_receipts WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): GoodsReceipt
    {
        return $this->find($uid) ?? throw new RuntimeException("Goods receipt \"{$uid}\" was not found.");
    }

    /**
     * @return GoodsReceipt[] oldest first
     */
    public function forPurchaseOrder(string $purchaseOrderUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_purchasing_receipts WHERE purchase_order_uid = :purchase_order_uid ORDER BY received_at ASC'
        );
        $statement->execute(['purchase_order_uid' => $purchaseOrderUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): GoodsReceipt
    {
        return new GoodsReceipt(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            purchaseOrderUid: $row['purchase_order_uid'],
            warehouseUid: $row['warehouse_uid'],
            receivedAt: new \DateTimeImmutable($row['received_at']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
