<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Export;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\ExportProviderInterface;
use Kontor\SDK\DTO\ExportContext;

/**
 * kontor.md#9.7. $fields is whitelisted before interpolation, same fix
 * applied to kontor/contacts' export providers.
 */
final class ItemExportProvider implements ExportProviderInterface
{
    private const FIELD_SQL = [
        'uid' => 'uid',
        'item_type' => 'item_type',
        'sku' => 'sku',
        'barcode' => 'barcode',
        'category_uid' => 'category_uid',
        'unit_code' => 'unit_code',
        'tax_code' => 'tax_code',
        'sales_price_minor' => 'sales_price_minor',
        'sales_currency' => 'sales_currency',
        'purchase_price_minor' => 'purchase_price_minor',
        'purchase_currency' => 'purchase_currency',
        'cost_price_minor' => 'cost_price_minor',
        'cost_currency' => 'cost_currency',
        'track_inventory' => 'track_inventory',
        'status' => 'status',
        'title_en' => "JSON_UNQUOTE(JSON_EXTRACT(title_json, '$.en'))",
        'description_en' => "JSON_UNQUOTE(JSON_EXTRACT(description_json, '$.en'))",
        'title_fr' => "JSON_UNQUOTE(JSON_EXTRACT(title_json, '$.fr'))",
        'description_fr' => "JSON_UNQUOTE(JSON_EXTRACT(description_json, '$.fr'))",
        'title_de' => "JSON_UNQUOTE(JSON_EXTRACT(title_json, '$.de'))",
        'description_de' => "JSON_UNQUOTE(JSON_EXTRACT(description_json, '$.de'))",
        'title_es' => "JSON_UNQUOTE(JSON_EXTRACT(title_json, '$.es'))",
        'description_es' => "JSON_UNQUOTE(JSON_EXTRACT(description_json, '$.es'))",
        'created_at' => 'created_at',
    ];

    private const ALLOWED_FILTERS = ['status', 'item_type', 'category_uid'];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function entityType(): string
    {
        return 'catalog_item';
    }

    public function fields(): array
    {
        return array_keys(self::FIELD_SQL);
    }

    public function filters(): array
    {
        return self::ALLOWED_FILTERS;
    }

    public function count(array $filters, ExportContext $context): int
    {
        [$where, $params] = $this->buildWhere($filters, $context);

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM kontor_catalog_items WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function iterate(array $filters, array $fields, ExportContext $context): iterable
    {
        $allFields = array_keys(self::FIELD_SQL);
        $selectedFields = $fields === [] ? $allFields : array_values(array_intersect($fields, $allFields));

        if ($selectedFields === []) {
            $selectedFields = $allFields;
        }

        [$where, $params] = $this->buildWhere($filters, $context);
        $columns = implode(', ', array_map(
            static fn (string $field): string => self::FIELD_SQL[$field] . " AS `{$field}`",
            $selectedFields,
        ));

        $statement = $this->pdo->prepare("SELECT {$columns} FROM kontor_catalog_items WHERE {$where} ORDER BY id ASC");
        $statement->execute($params);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        foreach ($statement as $row) {
            yield $row;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters, ExportContext $context): array
    {
        $organizationId = $this->organizations->internalIdOf($context->organizationId);
        $conditions = ['organization_id = :organization_id'];
        $params = ['organization_id' => $organizationId];

        foreach (self::ALLOWED_FILTERS as $filterKey) {
            if (isset($filters[$filterKey])) {
                $conditions[] = "{$filterKey} = :filter_{$filterKey}";
                $params["filter_{$filterKey}"] = $filters[$filterKey];
            }
        }

        return [implode(' AND ', $conditions), $params];
    }
}
