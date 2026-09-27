<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Search;

use Kontor\Core\Infrastructure\Database\TranslatingPDO;
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
        $subtitleSelect = $this->subtitleColumn !== null ? "{$this->subtitleColumn} AS subtitle" : 'NULL AS subtitle';
        $additionalWhere = $this->additionalWhere();

        if($this->usesLikeFallback()) {
            $condition = $this->likeCondition();
            $sql = "SELECT {$this->uidColumn} AS uid, {$this->titleColumn} AS title, {$subtitleSelect},
                        1.0 AS score
                    FROM {$this->table}
                    WHERE organization_id = :organization_id
                        AND ({$condition})
                        {$additionalWhere}
                    ORDER BY title ASC
                    LIMIT :limit OFFSET :offset";
        } else {
            $matchColumns = implode(', ', $this->fullTextColumns);
            $sql = "SELECT {$this->uidColumn} AS uid, {$this->titleColumn} AS title, {$subtitleSelect},
                        MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE) AS score
                    FROM {$this->table}
                    WHERE organization_id = :organization_id
                        AND MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE)
                        {$additionalWhere}
                    ORDER BY score DESC
                    LIMIT :limit OFFSET :offset";
        }

        $statement = $this->pdo->prepare($sql);
        $this->bindSearchTerm($statement, $query->term);
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
        $additionalWhere = $this->additionalWhere();

        if($this->usesLikeFallback()) {
            $condition = $this->likeCondition();
            $sql = "SELECT COUNT(*) FROM {$this->table}
                    WHERE organization_id = :organization_id
                        AND ({$condition})
                        {$additionalWhere}";
        } else {
            $matchColumns = implode(', ', $this->fullTextColumns);
            $sql = "SELECT COUNT(*) FROM {$this->table}
                    WHERE organization_id = :organization_id
                        AND MATCH({$matchColumns}) AGAINST (:term IN NATURAL LANGUAGE MODE)
                        {$additionalWhere}";
        }

        $statement = $this->pdo->prepare($sql);
        $this->bindSearchTerm($statement, $query->term);
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function usesLikeFallback(): bool
    {
        if($this->pdo instanceof TranslatingPDO) {
            return $this->pdo->dialectName() === 'sqlite';
        }

        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function likeCondition(): string
    {
        return implode(' OR ', array_map(
            static fn (string $column): string => "LOWER(COALESCE({$column}, '')) LIKE LOWER(:term_like) ESCAPE '!'",
            $this->fullTextColumns
        ));
    }

    private function bindSearchTerm(\PDOStatement $statement, string $term): void
    {
        if($this->usesLikeFallback()) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $statement->bindValue(':term_like', '%' . $escaped . '%');
            return;
        }

        $statement->bindValue(':term', $term);
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
