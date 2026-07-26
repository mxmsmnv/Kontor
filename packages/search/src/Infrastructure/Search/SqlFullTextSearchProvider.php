<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Search;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

/**
 * Substage 2.4 "SQL search" — a reusable MySQL FULLTEXT-backed
 * SearchProviderInterface. Business components (Contacts, CRM, ...) that
 * already declare a FULLTEXT index on their own table (e.g.
 * kontor.md#12.1's `FULLTEXT display_name, email`) construct one of these
 * instead of writing the query by hand each time.
 *
 * Table/column names are developer-supplied configuration, never
 * user input, so they're safe to interpolate directly; $query->term is
 * always bound as a parameter. $query->organizationId is the public uid
 * (kontor.md#10.3); it's resolved to the internal organization_id BIGINT
 * (kontor.md#10.4) via OrganizationRepository before binding — the same
 * fix AuditLogger needed in Substage 1.3, applied here up front.
 */
final class SqlFullTextSearchProvider implements SearchProviderInterface
{
    /**
     * @param string[] $fullTextColumns columns covered by the table's FULLTEXT index
     * @param string[] $additionalConditions developer-defined SQL conditions,
     *   such as lifecycle filters for archived or deleted records
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
        private readonly string $providerName,
        private readonly string $entityType,
        private readonly string $table,
        private readonly string $uidColumn,
        private readonly string $titleColumn,
        private readonly ?string $subtitleColumn,
        private readonly array $fullTextColumns,
        private readonly array $additionalConditions = [],
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === $this->entityType;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $organizationId = $this->organizations->internalIdOf($query->organizationId);
        $matchColumns = implode(', ', $this->fullTextColumns);
        $subtitleSelect = $this->subtitleColumn !== null ? "{$this->subtitleColumn} AS subtitle" : 'NULL AS subtitle';
        $additionalWhere = $this->additionalWhere();

        $sql = "SELECT {$this->uidColumn} AS uid, {$this->titleColumn} AS title, {$subtitleSelect},
                    MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE) AS score
                FROM {$this->table}
                WHERE organization_id = :organization_id
                    AND MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE)
                    {$additionalWhere}
                ORDER BY score DESC
                LIMIT :limit OFFSET :offset";

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':term', $query->term);
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $query->limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $query->offset, \PDO::PARAM_INT);
        $statement->execute();

        $hits = array_map(
            fn (array $row): SearchHit => new SearchHit(
                entityType: $this->entityType,
                entityUid: (string) $row['uid'],
                title: (string) $row['title'],
                subtitle: $row['subtitle'] !== null ? (string) $row['subtitle'] : null,
                score: (float) $row['score'],
            ),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new SearchResult($hits, $this->countMatches($query, $organizationId));
    }

    private function countMatches(SearchQuery $query, int $organizationId): int
    {
        $matchColumns = implode(', ', $this->fullTextColumns);
        $additionalWhere = $this->additionalWhere();

        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE organization_id = :organization_id
                    AND MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE)
                    {$additionalWhere}";

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':term', $query->term);
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function additionalWhere(): string
    {
        if ($this->additionalConditions === []) {
            return '';
        }

        return 'AND ' . implode(
            ' AND ',
            array_map(static fn (string $condition): string => "({$condition})", $this->additionalConditions)
        );
    }
}
