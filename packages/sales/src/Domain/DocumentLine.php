<?php

declare(strict_types=1);

namespace Kontor\Sales\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.4 — shared by quotations, orders (this substage) and
 * invoices (Substage 4.3). Substage 4.1 "document lines" milestone
 * includes real subtotal/discount/tax/total calculation, not just storage.
 *
 * $discountValue for a 'fixed' discount is a decimal major-unit amount
 * (e.g. 5.00 meaning 5 currency units), converted to minor units assuming
 * 2 decimal places — correct for most currencies but not universally (e.g.
 * JPY has 0, BHD has 3); kontor_document_lines.discount_value_decimal is a
 * generic DECIMAL(20,6), not a Money-shaped pair of columns, so this
 * assumption is inherent to the schema, not just this implementation.
 */
final class DocumentLine
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $documentType,
        public string $documentUid,
        public ?string $itemUid,
        public ?string $itemType,
        public ?string $sku,
        public string $title,
        public ?string $description,
        public float $quantity,
        public string $unitCode,
        public Money $unitPrice,
        public ?string $discountType,
        public float $discountValue,
        public ?string $taxCode,
        public float $taxRate,
        public int $sortOrder,
        public array $snapshot = [],
    ) {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function create(
        string $organizationId,
        string $documentType,
        string $documentUid,
        string $title,
        float $quantity,
        Money $unitPrice,
        ?string $itemUid = null,
        ?string $itemType = null,
        ?string $sku = null,
        ?string $description = null,
        string $unitCode = 'pcs',
        ?string $discountType = null,
        float $discountValue = 0.0,
        ?string $taxCode = null,
        float $taxRate = 0.0,
        int $sortOrder = 0,
        array $snapshot = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            documentType: $documentType,
            documentUid: $documentUid,
            itemUid: $itemUid,
            itemType: $itemType,
            sku: $sku,
            title: $title,
            description: $description,
            quantity: $quantity,
            unitCode: $unitCode,
            unitPrice: $unitPrice,
            discountType: $discountType,
            discountValue: $discountValue,
            taxCode: $taxCode,
            taxRate: $taxRate,
            sortOrder: $sortOrder,
            snapshot: $snapshot,
        );
    }

    public function grossAmount(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function discountAmount(): Money
    {
        $gross = $this->grossAmount();

        return match ($this->discountType) {
            'percentage' => $gross->multiply($this->discountValue / 100),
            'fixed' => Money::ofMinor((int) round($this->discountValue * 100), $gross->currencyCode()),
            default => Money::zero($gross->currencyCode()),
        };
    }

    public function subtotal(): Money
    {
        return $this->grossAmount()->subtract($this->discountAmount());
    }

    public function taxAmount(): Money
    {
        return $this->subtotal()->multiply($this->taxRate / 100);
    }

    public function total(): Money
    {
        return $this->subtotal()->add($this->taxAmount());
    }
}
