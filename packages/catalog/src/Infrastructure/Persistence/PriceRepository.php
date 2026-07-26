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

    /**
     * @return array<int, PriceListEntry>
     */
    public function forPriceList(string $priceListUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_catalog_prices
             WHERE price_list_uid = :price_list_uid
             ORDER BY item_uid ASC, min_quantity ASC'
        );
        $statement->execute(['price_list_uid' => $priceListUid]);

        return array_map(fn (array $row): PriceListEntry => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, string> $priceListUids
     * @return array<string, int>
     */
    public function countsForPriceLists(array $priceListUids): array
    {
        if ($priceListUids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($priceListUids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT price_list_uid, COUNT(*) AS entry_count
             FROM kontor_catalog_prices
             WHERE price_list_uid IN ({$placeholders})
             GROUP BY price_list_uid"
        );
        $statement->execute(array_values($priceListUids));
        $counts = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[$row['price_list_uid']] = (int) $row['entry_count'];
        }

        return $counts;
    }

    public function delete(string $priceListUid, string $itemUid, float $minQuantity): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM kontor_catalog_prices
             WHERE price_list_uid = :price_list_uid
               AND item_uid = :item_uid
               AND min_quantity = :min_quantity'
        );
        $statement->execute([
            'price_list_uid' => $priceListUid,
            'item_uid' => $itemUid,
            'min_quantity' => $minQuantity,
        ]);

        return $statement->rowCount() === 1;
    }

    public function replace(
        string $originalItemUid,
        float $originalMinQuantity,
        PriceListEntry $entry,
    ): void {
        $this->pdo->beginTransaction();

        try {
            if ($originalItemUid !== $entry->itemUid || $originalMinQuantity !== $entry->minQuantity) {
                $this->delete($entry->priceListUid, $originalItemUid, $originalMinQuantity);
            }

            $this->save($entry);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
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
