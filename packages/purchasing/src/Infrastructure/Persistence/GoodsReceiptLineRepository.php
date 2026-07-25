<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Purchasing\Domain\GoodsReceiptLine;
use Kontor\SDK\ValueObjects\Uid;

final class GoodsReceiptLineRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(GoodsReceiptLine $line): void
    {
        $organizationId = $this->organizations->internalIdOf($line->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_purchasing_receipt_lines
                (uid, organization_id, receipt_uid, po_line_uid, item_uid, quantity_received, unit_code)
             VALUES
                (:uid, :organization_id, :receipt_uid, :po_line_uid, :item_uid, :quantity_received, :unit_code)'
        );

        $statement->execute([
            'uid' => $line->uid->toString(),
            'organization_id' => $organizationId,
            'receipt_uid' => $line->receiptUid,
            'po_line_uid' => $line->poLineUid,
            'item_uid' => $line->itemUid,
            'quantity_received' => $line->quantityReceived,
            'unit_code' => $line->unitCode,
        ]);
    }

    /**
     * @return GoodsReceiptLine[]
     */
    public function forReceipt(string $receiptUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_purchasing_receipt_lines WHERE receipt_uid = :receipt_uid');
        $statement->execute(['receipt_uid' => $receiptUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * How much of a given purchase-order line has been received so far,
     * across every receipt ever recorded against it — what
     * GoodsReceiptService uses to enforce "can't receive more than was
     * ordered" and to recompute the order's overall status.
     */
    public function totalReceivedFor(string $poLineUid): float
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(quantity_received), 0) FROM kontor_purchasing_receipt_lines WHERE po_line_uid = :po_line_uid');
        $statement->execute(['po_line_uid' => $poLineUid]);

        return (float) $statement->fetchColumn();
    }

    private function hydrate(array $row): GoodsReceiptLine
    {
        return new GoodsReceiptLine(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            receiptUid: $row['receipt_uid'],
            poLineUid: $row['po_line_uid'],
            itemUid: $row['item_uid'],
            quantityReceived: (float) $row['quantity_received'],
            unitCode: $row['unit_code'],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
