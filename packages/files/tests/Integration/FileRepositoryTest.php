<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Integration;

use Kontor\Files\Infrastructure\Persistence\FileRepository;

final class FileRepositoryTest extends DatabaseTestCase
{
    public function test_insert_then_find_round_trips(): void
    {
        $files = new FileRepository($this->pdo);

        $uid = $files->insert(
            organizationId: 1,
            storage: 'local',
            path: 'org-1/abc-report.csv',
            originalName: 'report.csv',
            mimeType: 'text/csv',
            sizeBytes: 42,
            checksum: hash('sha256', 'x'),
            visibility: 'private',
            classification: 'financial',
            entityType: 'invoice',
            entityUid: 'inv_01',
            versionNumber: 1,
            metadata: ['source' => 'export'],
            createdBy: null,
        );

        $row = $files->find($uid);

        $this->assertSame('report.csv', $row['original_name']);
        $this->assertSame(1, (int) $row['version_number']);
        $this->assertSame('{"source":"export"}', $row['metadata_json']);
        $this->assertNull($row['archived_at']);
    }

    public function test_find_current_version_ignores_archived_rows(): void
    {
        $files = new FileRepository($this->pdo);

        $v1 = $files->insert(1, 'local', 'p1', 'contract.pdf', null, 1, 'c1', 'private', null, 'deal', 'deal_01', 1, [], null);
        $files->archive($v1);
        $v2 = $files->insert(1, 'local', 'p2', 'contract.pdf', null, 1, 'c2', 'private', null, 'deal', 'deal_01', 2, [], null);

        $current = $files->findCurrentVersion('deal', 'deal_01', 'contract.pdf');

        $this->assertSame($v2, $current['uid']);
    }

    public function test_version_history_orders_newest_first(): void
    {
        $files = new FileRepository($this->pdo);

        $v1 = $files->insert(1, 'local', 'p1', 'contract.pdf', null, 1, 'c1', 'private', null, 'deal', 'deal_01', 1, [], null);
        $files->archive($v1);
        $v2 = $files->insert(1, 'local', 'p2', 'contract.pdf', null, 1, 'c2', 'private', null, 'deal', 'deal_01', 2, [], null);

        $history = $files->versionHistory('deal', 'deal_01', 'contract.pdf');

        $this->assertCount(2, $history);
        $this->assertSame($v2, $history[0]['uid']);
        $this->assertSame($v1, $history[1]['uid']);
    }

    public function test_archive_then_restore(): void
    {
        $files = new FileRepository($this->pdo);
        $uid = $files->insert(1, 'local', 'p1', 'notes.txt', null, 1, 'c1', 'private', null, null, null, 1, [], null);

        $files->archive($uid);
        $this->assertNotNull($files->find($uid)['archived_at']);

        $files->restore($uid);
        $this->assertNull($files->find($uid)['archived_at']);
    }

    public function test_for_entity_returns_only_active_files(): void
    {
        $files = new FileRepository($this->pdo);
        $files->insert(1, 'local', 'p1', 'a.txt', null, 1, 'c1', 'private', null, 'contact', 'ct_01', 1, [], null);
        $archived = $files->insert(1, 'local', 'p2', 'b.txt', null, 1, 'c2', 'private', null, 'contact', 'ct_01', 1, [], null);
        $files->archive($archived);

        $active = $files->forEntity('contact', 'ct_01');

        $this->assertCount(1, $active);
        $this->assertSame('a.txt', $active[0]['original_name']);
    }
}
