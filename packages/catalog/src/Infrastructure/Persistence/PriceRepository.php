<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\SDK\ValueObjects\Money;

/**
 * kontor.md#14.3
 */
final class PriceRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function save(PriceListEntry $entry): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_catalog_prices
                (price_list_uid, item_uid, price_minor, currency_code, min_quantity, valid_from, valid_to)
             VALUES
                (:price_list_uid, :item_uid, :price_minor, :currency_code, :min_quantity, :valid_from, :valid_to)
             ON DUPLICATE KEY UPDATE
                price_minor = VALUES(price_minor), currency_code = VALUES(currency_code),
                valid_from = VALUES(valid_from), valid_to = VALUES(valid_to)'
        );

        $statement->execute([
            'price_list_uid' => $entry->priceListUid,
            'item_uid' => $entry->itemUid,
            'price_minor' => $entry->price->amountMinor(),
            'currency_code' => $entry->price->currencyCode(),
            'min_quantity' => $entry->minQuantity,
            'valid_from' => $entry->validFrom?->format('Y-m-d'),
            'valid_to' => $entry->validTo?->format('Y-m-d'),
        ]);
    }

    /**
     * @return array<int, PriceListEntry> ordered by min_quantity descending
     *   (highest tier first), the order PricingService needs
     */
    public function forItem(string $priceListUid, string $itemUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_catalog_prices
             WHERE price_list_uid = :price_list_uid AND item_uid = :item_uid
             ORDER BY min_quantity DESC'
        );
        $statement->execute(['price_list_uid' => $priceListUid, 'item_uid' => $itemUid]);

        return array_map(fn (array $row): PriceListEntry => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): PriceListEntry
    {
        return new PriceListEntry(
            priceListUid: $row['price_list_uid'],
            itemUid: $row['item_uid'],
            price: Money::ofMinor((int) $row['price_minor'], $row['currency_code']),
            minQuantity: (float) $row['min_quantity'],
            validFrom: $row['valid_from'] !== null ? new \DateTimeImmutable($row['valid_from']) : null,
            validTo: $row['valid_to'] !== null ? new \DateTimeImmutable($row['valid_to']) : null,
        );
    }
}
