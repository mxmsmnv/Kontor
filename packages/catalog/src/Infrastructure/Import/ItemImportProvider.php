<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Import;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;
use Kontor\SDK\ValueObjects\Money;

/**
 * kontor.md#9.6. CSV/XLSX rows are flat, so multi-language title/description
 * (kontor.md#14.1's title_json/description_json) come in as one flat
 * column per locale — title_en, title_fr, title_de, title_es — rather than
 * a nested JSON cell, matching the flat-row shape every format reader in
 * kontor/core already produces.
 */
final class ItemImportProvider implements ImportProviderInterface
{
    private const LOCALES = ['en', 'fr', 'de', 'es'];

    public function __construct(
        private readonly CatalogItemRepository $items,
        private readonly UnitOfMeasure $units = new UnitOfMeasure(),
        private readonly TaxCode $taxCodes = new TaxCode(),
    ) {
    }

    public function entityType(): string
    {
        return 'catalog_item';
    }

    public function fields(): array
    {
        $fields = ['item_type', 'sku', 'barcode', 'category_uid', 'unit_code', 'tax_code',
            'sales_price_minor', 'sales_currency', 'purchase_price_minor', 'purchase_currency',
            'cost_price_minor', 'cost_currency', 'track_inventory', 'status'];

        foreach (self::LOCALES as $locale) {
            $fields[] = "title_{$locale}";
            $fields[] = "description_{$locale}";
        }

        return $fields;
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        $errors = [];

        if ($this->extractLocalized($record, 'title') === []) {
            $errors['title'][] = 'catalog_item.title.required';
        }

        $unitCode = $this->stringOrNull($record['unit_code'] ?? null);

        if ($unitCode !== null && !$this->units->isKnown($unitCode)) {
            $errors['unit_code'][] = 'catalog_item.unit_code.unknown';
        }

        $taxCode = $this->stringOrNull($record['tax_code'] ?? null);

        if ($taxCode !== null && !$this->taxCodes->isKnown($taxCode)) {
            $errors['tax_code'][] = 'catalog_item.tax_code.unknown';
        }

        foreach (['sales', 'purchase', 'cost'] as $priceField) {
            $minor = $record["{$priceField}_price_minor"] ?? null;
            $currency = $this->stringOrNull($record["{$priceField}_currency"] ?? null);

            if (($minor !== null && $minor !== '') !== ($currency !== null)) {
                $errors["{$priceField}_price_minor"][] = "catalog_item.{$priceField}_price.currency_required";
            }
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        $sku = $this->stringOrNull($record['sku'] ?? null);

        if ($sku === null) {
            return null;
        }

        return $this->items->findBySku($context->organizationId, $sku)?->uid->toString();
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $existingUid = $this->findExisting($record, $context);

        if ($existingUid !== null) {
            $item = $this->items->require($existingUid);
            $this->applyRecord($item, $record);
            $this->items->save($item);

            return new ImportRecordResult('updated', $existingUid);
        }

        $item = CatalogItem::create(
            organizationId: $context->organizationId,
            title: $this->extractLocalized($record, 'title'),
            itemType: $record['item_type'] ?? 'product',
            sku: $this->stringOrNull($record['sku'] ?? null),
            barcode: $this->stringOrNull($record['barcode'] ?? null),
            description: $this->extractLocalized($record, 'description'),
            categoryUid: $this->stringOrNull($record['category_uid'] ?? null),
            unitCode: $this->stringOrNull($record['unit_code'] ?? null) ?? 'pcs',
            taxCode: $this->stringOrNull($record['tax_code'] ?? null),
            salesPrice: $this->moneyFromRecord($record, 'sales'),
            purchasePrice: $this->moneyFromRecord($record, 'purchase'),
            costPrice: $this->moneyFromRecord($record, 'cost'),
            trackInventory: $this->boolFromRecord($record['track_inventory'] ?? null),
            status: $record['status'] ?? 'active',
        );

        $this->items->save($item);

        return new ImportRecordResult('created', $item->uid->toString());
    }

    /**
     * @param array<string, mixed> $record
     */
    private function applyRecord(CatalogItem $item, array $record): void
    {
        foreach (['item_type', 'sku', 'barcode', 'category_uid', 'unit_code', 'tax_code', 'status'] as $field) {
            if (array_key_exists($field, $record)) {
                $item->{$this->toProperty($field)} = $this->stringOrNull($record[$field]) ?? $record[$field];
            }
        }

        $title = $this->extractLocalized($record, 'title');

        if ($title !== []) {
            $item->title = $title;
        }

        $description = $this->extractLocalized($record, 'description');

        if ($description !== []) {
            $item->description = $description;
        }

        if (array_key_exists('track_inventory', $record)) {
            $item->trackInventory = $this->boolFromRecord($record['track_inventory']);
        }

        foreach (['sales', 'purchase', 'cost'] as $priceField) {
            if (array_key_exists("{$priceField}_price_minor", $record)) {
                $property = $priceField . 'Price';
                $item->$property = $this->moneyFromRecord($record, $priceField);
            }
        }
    }

    private function toProperty(string $field): string
    {
        return match ($field) {
            'item_type' => 'itemType',
            'category_uid' => 'categoryUid',
            'unit_code' => 'unitCode',
            'tax_code' => 'taxCode',
            default => $field,
        };
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, string>
     */
    private function extractLocalized(array $record, string $prefix): array
    {
        $result = [];

        foreach (self::LOCALES as $locale) {
            $value = $this->stringOrNull($record["{$prefix}_{$locale}"] ?? null);

            if ($value !== null) {
                $result[$locale] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function moneyFromRecord(array $record, string $prefix): ?Money
    {
        $minor = $record["{$prefix}_price_minor"] ?? null;
        $currency = $this->stringOrNull($record["{$prefix}_currency"] ?? null);

        if ($minor === null || $minor === '' || $currency === null) {
            return null;
        }

        return Money::ofMinor((int) $minor, $currency);
    }

    private function boolFromRecord(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(is_string($value) ? strtolower(trim($value)) : $value, [true, 1, '1', 'true', 'yes'], true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
