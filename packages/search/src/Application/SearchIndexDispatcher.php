<?php

declare(strict_types=1);

namespace Kontor\Search\Application;

use Kontor\Search\Infrastructure\Queue\SearchIndexJob;
use Kontor\SDK\Contracts\QueueInterface;

/**
 * Convenience wrapper components call after writing an entity, to
 * asynchronously push it to whatever SearchIndexerInterface implementations
 * are registered for its entity type (kontor.md section 28 external search
 * engines) — via the "queue" capability KontorQueue already provides.
 */
final class SearchIndexDispatcher
{
    public function __construct(private readonly QueueInterface $queue)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function reindex(string $entityType, string $entityUid, array $data): string
    {
        return $this->queue->dispatch(SearchIndexJob::forIndex($entityType, $entityUid, $data));
    }

    public function removeFromIndex(string $entityType, string $entityUid): string
    {
        return $this->queue->dispatch(SearchIndexJob::forRemoval($entityType, $entityUid));
    }
}
