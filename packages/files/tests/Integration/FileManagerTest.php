<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Files\Application\FileManager;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Files\Tests\Support\RecordingEventDispatcher;

final class FileManagerTest extends DatabaseTestCase
{
    private string $storageRoot;
    private string $organizationUid;

    protected function setUp(): void
    {
        // set before parent::setUp(), which markTestSkipped()s (throwing) when
        // no test database is configured — tearDown() still runs after a skip
        // and must find storageRoot initialized.
        $this->storageRoot = sys_get_temp_dir() . '/kontor-files-manager-' . bin2hex(random_bytes(6));

        parent::setUp();

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storageRoot);
        parent::tearDown();
    }

    private function manager(?RecordingEventDispatcher $events = null): FileManager
    {
        $storage = new LocalPrivateStorage($this->storageRoot, new SignedUrlSigner('secret', 'https://example.test/download'));

        return new FileManager(
            $storage,
            new FileRepository($this->pdo),
            new OrganizationRepository($this->pdo),
            $events,
        );
    }

    public function test_upload_stores_the_file_and_metadata(): void
    {
        $result = $this->manager()->upload($this->organizationUid, 'invoice.pdf', 'pdf-bytes');

        $this->assertSame(1, $result['versionNumber']);

        $manager = $this->manager();
        $file = $manager->find($result['uid']);
        $this->assertSame('invoice.pdf', $file['original_name']);

        $handle = $manager->read($result['uid']);
        $this->assertSame('pdf-bytes', stream_get_contents($handle));
        fclose($handle);
    }

    public function test_uploading_again_for_the_same_entity_and_name_creates_a_new_version(): void
    {
        $manager = $this->manager();

        $first = $manager->upload(
            $this->organizationUid,
            'contract.pdf',
            'v1',
            entityType: 'deal',
            entityUid: 'deal_01',
        );
        $second = $manager->upload(
            $this->organizationUid,
            'contract.pdf',
            'v2',
            entityType: 'deal',
            entityUid: 'deal_01',
        );

        $this->assertSame(1, $first['versionNumber']);
        $this->assertSame(2, $second['versionNumber']);

        $history = $manager->versionHistory('deal', 'deal_01', 'contract.pdf');
        $this->assertCount(2, $history);

        // the old version is archived but its bytes are still readable
        $oldHandle = $manager->read($first['uid']);
        $this->assertSame('v1', stream_get_contents($oldHandle));
        fclose($oldHandle);
    }

    public function test_upload_without_an_entity_never_creates_a_version(): void
    {
        $manager = $this->manager();

        $first = $manager->upload($this->organizationUid, 'standalone.txt', 'a');
        $second = $manager->upload($this->organizationUid, 'standalone.txt', 'b');

        $this->assertSame(1, $first['versionNumber']);
        $this->assertSame(1, $second['versionNumber']);
        $this->assertNotSame($first['uid'], $second['uid']);
    }

    public function test_archive_and_restore(): void
    {
        $manager = $this->manager();
        $uid = $manager->upload($this->organizationUid, 'a.txt', 'x')['uid'];

        $manager->archive($uid, $this->organizationUid);
        $this->assertNotNull($manager->find($uid)['archived_at']);

        $manager->restore($uid, $this->organizationUid);
        $this->assertNull($manager->find($uid)['archived_at']);
    }

    public function test_temporary_url_is_signed_and_events_are_emitted(): void
    {
        $events = new RecordingEventDispatcher();
        $manager = $this->manager($events);
        $uid = $manager->upload($this->organizationUid, 'a.txt', 'x')['uid'];

        $url = $manager->temporaryUrl($uid, (new \DateTimeImmutable())->modify('+1 hour'), $this->organizationUid);

        $this->assertStringContainsString('signature=', $url);
        $this->assertCount(1, $events->eventsNamed('file.uploaded'));
        $this->assertCount(1, $events->eventsNamed('file.shared'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
