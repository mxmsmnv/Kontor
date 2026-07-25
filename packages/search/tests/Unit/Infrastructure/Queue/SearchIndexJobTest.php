<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Infrastructure\Queue;

use Kontor\Search\Infrastructure\Queue\SearchIndexJob;
use Kontor\Search\Infrastructure\Registry\SearchIndexerRegistry;
use Kontor\Search\SearchIndexerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SearchIndexJobTest extends TestCase
{
    public function test_for_index_carries_the_expected_payload(): void
    {
        $job = SearchIndexJob::forIndex('contact', 'ct_01', ['name' => 'Acme']);

        $this->assertSame('search.index', $job->jobType());
        $this->assertSame(
            ['action' => 'index', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => ['name' => 'Acme']],
            $job->payload()
        );
    }

    public function test_handle_indexes_via_every_matching_indexer(): void
    {
        $recorder = new RecordingIndexer('contact');
        $registry = new SearchIndexerRegistry();
        $registry->register($recorder);

        $job = new SearchIndexJob(
            ['action' => 'index', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => ['name' => 'Acme']],
            $registry,
        );

        $job->handle($job->payload(), new NullProgressReporter());

        $this->assertSame([['index', 'contact', 'ct_01', ['name' => 'Acme']]], $recorder->calls);
    }

    public function test_handle_removes_via_every_matching_indexer(): void
    {
        $recorder = new RecordingIndexer('contact');
        $registry = new SearchIndexerRegistry();
        $registry->register($recorder);

        $job = new SearchIndexJob(
            ['action' => 'remove', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => []],
            $registry,
        );

        $job->handle($job->payload(), new NullProgressReporter());

        $this->assertSame([['remove', 'contact', 'ct_01', null]], $recorder->calls);
    }

    public function test_handle_skips_indexers_that_do_not_support_the_entity_type(): void
    {
        $recorder = new RecordingIndexer('deal');
        $registry = new SearchIndexerRegistry();
        $registry->register($recorder);

        $job = new SearchIndexJob(
            ['action' => 'index', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => []],
            $registry,
        );

        $job->handle($job->payload(), new NullProgressReporter());

        $this->assertSame([], $recorder->calls);
    }

    public function test_handle_throws_without_an_indexer_registry(): void
    {
        $job = new SearchIndexJob(['action' => 'index', 'entityType' => 'contact', 'entityUid' => 'ct_01', 'data' => []]);

        $this->expectException(RuntimeException::class);

        $job->handle($job->payload(), new NullProgressReporter());
    }
}

final class RecordingIndexer implements SearchIndexerInterface
{
    /** @var list<array{0: string, 1: string, 2: string, 3: ?array}> */
    public array $calls = [];

    public function __construct(private readonly string $entityType)
    {
    }

    public function supports(string $entityType): bool
    {
        return $entityType === $this->entityType;
    }

    public function index(string $entityType, string $entityUid, array $data): void
    {
        $this->calls[] = ['index', $entityType, $entityUid, $data];
    }

    public function remove(string $entityType, string $entityUid): void
    {
        $this->calls[] = ['remove', $entityType, $entityUid, null];
    }
}

final class NullProgressReporter implements \Kontor\SDK\Contracts\JobProgressReporterInterface
{
    public function report(int $percent): void
    {
    }
}
