<?php

declare(strict_types=1);

namespace Kontor\Catalog\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#14.1. "products" and "services" (Substage 3.2) are both
 * CatalogItem, distinguished by $itemType — the schema is one items table,
 * not separate ones. title_json/description_json are multi-language
 * (locale code => text), matching kontor.md section 23's i18n approach —
 * unlike Contact's single display_name string.
 */
final class CatalogItem
{
    /**
     * @param array<string, string> $title locale => text
     * @param array<string, string> $description locale => text
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $itemType,
        public ?string $sku,
        public ?string $barcode,
        public array $title,
        public array $description,
        public ?string $categoryUid,
        public string $unitCode,
        public ?string $taxCode,
        public ?Money $salesPrice,
        public ?Money $purchasePrice,
        public ?Money $costPrice,
        public bool $trackInventory,
        public string $status,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, string> $title
     * @param array<string, string> $description
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        array $title,
        string $itemType = 'product',
        ?string $sku = null,
        ?string $barcode = null,
        array $description = [],
        ?string $categoryUid = null,
        string $unitCode = 'pcs',
        ?string $taxCode = null,
        ?Money $salesPrice = null,
        ?Money $purchasePrice = null,
        ?Money $costPrice = null,
        bool $trackInventory = false,
        string $status = 'active',
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            itemType: $itemType,
            sku: $sku,
            barcode: $barcode,
            title: $title,
            description: $description,
            categoryUid: $categoryUid,
            unitCode: $unitCode,
            taxCode: $taxCode,
            salesPrice: $salesPrice,
            purchasePrice: $purchasePrice,
            costPrice: $costPrice,
            trackInventory: $trackInventory,
            status: $status,
            metadata: $metadata,
        );
    }

    public function titleIn(string $locale, string $fallback = 'en'): ?string
    {
        return $this->title[$locale] ?? $this->title[$fallback] ?? null;
    }

    public function isService(): bool
    {
        return $this->itemType === 'service';
    }
}
