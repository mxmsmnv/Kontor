<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Search;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

/**
 * Searches the Catalog's localized JSON fields without requiring generated
 * columns for every language. Exact identifiers rank above textual matches.
 */
final class CatalogItemSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function name(): string
    {
        return 'catalog';
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'catalog_item';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $organizationId = $this->organizations->internalIdOf($query->organizationId);
        $escapedTerm = $this->escapeLike($query->term);
        $pattern = "%{$escapedTerm}%";
        $prefix = "{$escapedTerm}%";
        $where = $this->whereClause();

        $statement = $this->pdo->prepare(
            "SELECT uid, item_type, sku, barcode, title_json,
                CASE
                    WHEN LOWER(COALESCE(sku, '')) = LOWER(:score_sku)
                        OR LOWER(COALESCE(barcode, '')) = LOWER(:score_barcode) THEN 5
                    WHEN COALESCE(sku, '') LIKE :score_sku_prefix ESCAPE '!'
                        OR COALESCE(barcode, '') LIKE :score_barcode_prefix ESCAPE '!' THEN 4
                    WHEN CAST(title_json AS CHAR) LIKE :score_title ESCAPE '!' THEN 3
                    ELSE 1
                END AS score
             FROM kontor_catalog_items
             WHERE organization_id = :organization_id
                AND archived_at IS NULL
                AND status <> 'discontinued'
                AND {$where}
             ORDER BY score DESC, updated_at DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':score_sku', $query->term);
        $statement->bindValue(':score_barcode', $query->term);
        $statement->bindValue(':score_sku_prefix', $prefix);
        $statement->bindValue(':score_barcode_prefix', $prefix);
        $statement->bindValue(':score_title', $pattern);
        $this->bindSearch($statement, $organizationId, $pattern);
        $statement->bindValue(':limit', $query->limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $query->offset, \PDO::PARAM_INT);
        $statement->execute();

        $hits = array_map(
            fn (array $row): SearchHit => new SearchHit(
                entityType: 'catalog_item',
                entityUid: (string) $row['uid'],
                title: $this->localizedTitle((string) $row['title_json']),
                subtitle: $this->subtitle($row),
                url: 'catalog-item/?id=' . rawurlencode((string) $row['uid']),
                score: (float) $row['score'],
            ),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new SearchResult($hits, $this->countMatches($organizationId, $pattern));
    }

    private function countMatches(int $organizationId, string $pattern): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM kontor_catalog_items
             WHERE organization_id = :organization_id
                AND archived_at IS NULL
                AND status <> 'discontinued'
                AND {$this->whereClause()}"
        );
        $this->bindSearch($statement, $organizationId, $pattern);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function whereClause(): string
    {
        return "(CAST(title_json AS CHAR) LIKE :title_pattern ESCAPE '!'
            OR CAST(COALESCE(description_json, JSON_OBJECT()) AS CHAR) LIKE :description_pattern ESCAPE '!'
            OR COALESCE(sku, '') LIKE :sku_pattern ESCAPE '!'
            OR COALESCE(barcode, '') LIKE :barcode_pattern ESCAPE '!')";
    }

    private function bindSearch(\PDOStatement $statement, int $organizationId, string $pattern): void
    {
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);
        $statement->bindValue(':title_pattern', $pattern);
        $statement->bindValue(':description_pattern', $pattern);
        $statement->bindValue(':sku_pattern', $pattern);
        $statement->bindValue(':barcode_pattern', $pattern);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function localizedTitle(string $json): string
    {
        $titles = json_decode($json, true);

        if (!is_array($titles)) {
            return 'Catalog item';
        }

        $title = $titles['en'] ?? reset($titles);

        return is_string($title) && $title !== '' ? $title : 'Catalog item';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function subtitle(array $row): string
    {
        $type = (string) ($row['item_type'] ?? 'product') === 'service' ? 'Service' : 'Product';
        $identifier = (string) ($row['sku'] ?: $row['barcode']);

        return $identifier !== '' ? "{$identifier} · {$type}" : $type;
    }
}
