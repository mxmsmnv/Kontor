<?php

declare(strict_types=1);

namespace Kontor\Sales\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Sales\Domain\DocumentLine;

/**
 * kontor.md#15.4
 */
final class DocumentLineRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(DocumentLine $line): void
    {
        $organizationId = $this->organizations->internalIdOf($line->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_document_lines
                (uid, organization_id, document_type, document_uid, item_uid, item_type, sku, title, description,
                 quantity_decimal, unit_code, unit_price_minor, currency_code, discount_type, discount_value_decimal,
                 tax_code, tax_rate_decimal, tax_minor, subtotal_minor, total_minor, sort_order, snapshot_json)
             VALUES
                (:uid, :organization_id, :document_type, :document_uid, :item_uid, :item_type, :sku, :title, :description,
                 :quantity_decimal, :unit_code, :unit_price_minor, :currency_code, :discount_type, :discount_value_decimal,
                 :tax_code, :tax_rate_decimal, :tax_minor, :subtotal_minor, :total_minor, :sort_order, :snapshot_json)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title), description = VALUES(description), quantity_decimal = VALUES(quantity_decimal),
                unit_code = VALUES(unit_code), unit_price_minor = VALUES(unit_price_minor),
                discount_type = VALUES(discount_type), discount_value_decimal = VALUES(discount_value_decimal),
                tax_code = VALUES(tax_code), tax_rate_decimal = VALUES(tax_rate_decimal), tax_minor = VALUES(tax_minor),
                subtotal_minor = VALUES(subtotal_minor), total_minor = VALUES(total_minor), sort_order = VALUES(sort_order),
                snapshot_json = VALUES(snapshot_json)'
        );

        $statement->execute([
            'uid' => $line->uid->toString(),
            'organization_id' => $organizationId,
            'document_type' => $line->documentType,
            'document_uid' => $line->documentUid,
            'item_uid' => $line->itemUid,
            'item_type' => $line->itemType,
            'sku' => $line->sku,
            'title' => $line->title,
            'description' => $line->description,
            'quantity_decimal' => $line->quantity,
            'unit_code' => $line->unitCode,
            'unit_price_minor' => $line->unitPrice->amountMinor(),
            'currency_code' => $line->unitPrice->currencyCode(),
            'discount_type' => $line->discountType,
            'discount_value_decimal' => $line->discountValue,
            'tax_code' => $line->taxCode,
            'tax_rate_decimal' => $line->taxRate,
            'tax_minor' => $line->taxAmount()->amountMinor(),
            'subtotal_minor' => $line->subtotal()->amountMinor(),
            'total_minor' => $line->total()->amountMinor(),
            'sort_order' => $line->sortOrder,
            'snapshot_json' => $line->snapshot !== [] ? json_encode($line->snapshot, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    /**
     * @return array<int, DocumentLine> ordered by sort_order ascending
     */
    public function forDocument(string $documentType, string $documentUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_document_lines WHERE document_type = :document_type AND document_uid = :document_uid ORDER BY sort_order ASC'
        );
        $statement->execute(['document_type' => $documentType, 'document_uid' => $documentUid]);

        return array_map(fn (array $row): DocumentLine => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function deleteForDocument(string $documentType, string $documentUid): void
    {
        $statement = $this->pdo->prepare('DELETE FROM kontor_document_lines WHERE document_type = :document_type AND document_uid = :document_uid');
        $statement->execute(['document_type' => $documentType, 'document_uid' => $documentUid]);
    }

    private function hydrate(array $row): DocumentLine
    {
        return new DocumentLine(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            documentType: $row['document_type'],
            documentUid: $row['document_uid'],
            itemUid: $row['item_uid'],
            itemType: $row['item_type'],
            sku: $row['sku'],
            title: $row['title'],
            description: $row['description'],
            quantity: (float) $row['quantity_decimal'],
            unitCode: $row['unit_code'],
            unitPrice: Money::ofMinor((int) $row['unit_price_minor'], $row['currency_code']),
            discountType: $row['discount_type'],
            discountValue: (float) $row['discount_value_decimal'],
            taxCode: $row['tax_code'],
            taxRate: (float) $row['tax_rate_decimal'],
            sortOrder: (int) $row['sort_order'],
            snapshot: $row['snapshot_json'] !== null ? json_decode($row['snapshot_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
