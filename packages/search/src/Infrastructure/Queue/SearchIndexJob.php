<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Queue;

use Kontor\Search\Infrastructure\Registry\SearchIndexerRegistry;
use Kontor\SDK\Contracts\CacheInterface;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use RuntimeException;

/**
 * Substage 2.4 "indexing queue". Dispatched via KontorQueue by
 * SearchIndexDispatcher; the worker reconstructs it from the stored
 * job_type/payload through JobRegistry, whose factory closure supplies the
 * real SearchIndexerRegistry — the payload itself only ever carries plain
 * data (kontor.md's JobInterface pattern, established in Substage 2.1).
 */
final class SearchIndexJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly ?SearchIndexerRegistry $indexers = null,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function forIndex(string $entityType, string $entityUid, array $data): self
    {
        return new self(['action' => 'index', 'entityType' => $entityType, 'entityUid' => $entityUid, 'data' => $data]);
    }

    public static function forRemoval(string $entityType, string $entityUid): self
    {
        return new self(['action' => 'remove', 'entityType' => $entityType, 'entityUid' => $entityUid, 'data' => []]);
    }

    public function jobType(): string
    {
        return 'search.index';
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        if ($this->indexers === null) {
            throw new RuntimeException(
                'SearchIndexJob cannot run without a SearchIndexerRegistry — register its job factory '.
                'via JobRegistry::register(\'search.index\', ...) with the registry injected.'
            );
        }

        $entityType = $payload['entityType'];
        $entityUid = $payload['entityUid'];
        $isRemoval = ($payload['action'] ?? 'index') === 'remove';

        foreach ($this->indexers->forEntityType($entityType) as $indexer) {
            if ($isRemoval) {
                $indexer->remove($entityType, $entityUid);
            } else {
                $indexer->index($entityType, $entityUid, $payload['data'] ?? []);
            }
        }

        $this->cache?->flushTag('results');
        $progress->report(100);
    }
}
