<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#14.1. Implements RepositoryInterface (as ContactRepository
 * does) so it can register into RepositoryRegistry for ImportManager's
 * rollback.
 */
final class CatalogItemRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?CatalogItem
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_catalog_items WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): CatalogItem
    {
        return $this->find($id) ?? throw new RuntimeException("Catalog item \"{$id}\" was not found.");
    }

    public function findBySku(string $organizationUid, string $sku): ?CatalogItem
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_catalog_items WHERE organization_id = :organization_id AND sku = :sku');
        $statement->execute(['organization_id' => $organizationId, 'sku' => $sku]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof CatalogItem) {
            throw new InvalidArgumentException('CatalogItemRepository::save() expects a CatalogItem.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_catalog_items
                (uid, organization_id, item_type, sku, barcode, title_json, description_json, category_uid,
                 unit_code, tax_code, sales_price_minor, sales_currency, purchase_price_minor, purchase_currency,
                 cost_price_minor, cost_currency, track_inventory, status, metadata_json, created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :item_type, :sku, :barcode, :title_json, :description_json, :category_uid,
                 :unit_code, :tax_code, :sales_price_minor, :sales_currency, :purchase_price_minor, :purchase_currency,
                 :cost_price_minor, :cost_currency, :track_inventory, :status, :metadata_json, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                item_type = VALUES(item_type), sku = VALUES(sku), barcode = VALUES(barcode),
                title_json = VALUES(title_json), description_json = VALUES(description_json),
                category_uid = VALUES(category_uid), unit_code = VALUES(unit_code), tax_code = VALUES(tax_code),
                sales_price_minor = VALUES(sales_price_minor), sales_currency = VALUES(sales_currency),
                purchase_price_minor = VALUES(purchase_price_minor), purchase_currency = VALUES(purchase_currency),
                cost_price_minor = VALUES(cost_price_minor), cost_currency = VALUES(cost_currency),
                track_inventory = VALUES(track_inventory), status = VALUES(status),
                metadata_json = VALUES(metadata_json), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'item_type' => $entity->itemType,
            'sku' => $entity->sku,
            'barcode' => $entity->barcode,
            'title_json' => json_encode($entity->title, JSON_THROW_ON_ERROR),
            'description_json' => $entity->description !== [] ? json_encode($entity->description, JSON_THROW_ON_ERROR) : null,
            'category_uid' => $entity->categoryUid,
            'unit_code' => $entity->unitCode,
            'tax_code' => $entity->taxCode,
            'sales_price_minor' => $entity->salesPrice?->amountMinor(),
            'sales_currency' => $entity->salesPrice?->currencyCode(),
            'purchase_price_minor' => $entity->purchasePrice?->amountMinor(),
            'purchase_currency' => $entity->purchasePrice?->currencyCode(),
            'cost_price_minor' => $entity->costPrice?->amountMinor(),
            'cost_currency' => $entity->costPrice?->currencyCode(),
            'track_inventory' => $entity->trackInventory ? 1 : 0,
            'status' => $entity->status,
            'metadata_json' => $entity->metadata !== [] ? json_encode($entity->metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_catalog_items SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_catalog_items SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return CatalogItem[]
     */
    public function findAll(
        string $organizationUid,
        string $query = '',
        ?string $itemType = null,
        bool $archived = false,
        int $limit = 100,
        int $offset = 0,
    ): array {
        [$sql, $params] = $this->listQuery($organizationUid, $query, $itemType, $archived);
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', max(1, min($limit, 250)), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): CatalogItem => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    public function countMatching(
        string $organizationUid,
        string $query = '',
        ?string $itemType = null,
        bool $archived = false,
    ): int {
        [$sql, $params] = $this->listQuery($organizationUid, $query, $itemType, $archived, true);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{units: array<string, int>, taxes: array<string, int>}
     */
    public function referenceUsage(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $usage = ['units' => [], 'taxes' => []];

        foreach (['units' => 'unit_code', 'taxes' => 'tax_code'] as $group => $column) {
            $statement = $this->pdo->prepare(
                "SELECT {$column} AS reference_code, COUNT(*) AS item_count
                 FROM kontor_catalog_items
                 WHERE organization_id = :organization_id
                   AND archived_at IS NULL
                   AND {$column} IS NOT NULL
                   AND {$column} <> ''
                 GROUP BY {$column}"
            );
            $statement->execute(['organization_id' => $organizationId]);

            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $usage[$group][$row['reference_code']] = (int) $row['item_count'];
            }
        }

        return $usage;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function listQuery(
        string $organizationUid,
        string $query,
        ?string $itemType,
        bool $archived,
        bool $count = false,
    ): array {
        $params = ['organization_id' => $this->organizations->internalIdOf($organizationUid)];
        $sql = 'SELECT ' . ($count ? 'COUNT(*)' : '*') . ' FROM kontor_catalog_items
            WHERE organization_id = :organization_id
              AND archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        $query = trim($query);

        if ($itemType !== null) {
            $sql .= ' AND item_type = :item_type';
            $params['item_type'] = $itemType;
        }

        if ($query !== '') {
            $sql .= ' AND (
                sku LIKE :query
                OR barcode LIKE :query
                OR title_json LIKE :query
                OR description_json LIKE :query
            )';
            $params['query'] = '%' . $query . '%';
        }

        return [$sql, $params];
    }

    private function hydrate(array $row): CatalogItem
    {
        return new CatalogItem(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            itemType: $row['item_type'],
            sku: $row['sku'],
            barcode: $row['barcode'],
            title: json_decode($row['title_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            description: $row['description_json'] !== null ? json_decode($row['description_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            categoryUid: $row['category_uid'],
            unitCode: $row['unit_code'],
            taxCode: $row['tax_code'],
            salesPrice: $this->moneyFrom($row['sales_price_minor'], $row['sales_currency']),
            purchasePrice: $this->moneyFrom($row['purchase_price_minor'], $row['purchase_currency']),
            costPrice: $this->moneyFrom($row['cost_price_minor'], $row['cost_currency']),
            trackInventory: (bool) $row['track_inventory'],
            status: $row['status'],
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function moneyFrom(int|string|null $amountMinor, ?string $currencyCode): ?Money
    {
        if ($amountMinor === null || $currencyCode === null) {
            return null;
        }

        return Money::ofMinor((int) $amountMinor, $currencyCode);
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
