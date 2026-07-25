<?php

declare(strict_types=1);

namespace Kontor\Search;

/**
 * A push-based indexing extension point, distinct from
 * SearchProviderInterface (which only queries). Pure SQL FULLTEXT needs no
 * separate indexer — the index lives in the same table, auto-maintained by
 * MySQL on INSERT/UPDATE. An external engine (Meilisearch, Typesense,
 * OpenSearch, Elasticsearch — kontor.md section 28), which does need
 * entities pushed to it, implements this and registers with
 * SearchIndexerRegistry; SearchIndexJob (the Substage 2.4 "indexing queue"
 * milestone) calls it asynchronously via KontorQueue.
 */
interface SearchIndexerInterface
{
    public function supports(string $entityType): bool;

    /**
     * @param array<string, mixed> $data
     */
    public function index(string $entityType, string $entityUid, array $data): void;

    public function remove(string $entityType, string $entityUid): void;
}
