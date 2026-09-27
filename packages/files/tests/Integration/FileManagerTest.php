<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Domain\Organization;
use Kontor\Files\Application\FileManager;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Files\Tests\Support\RecordingEventDispatcher;
use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\DTO\StoredFile;

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

    public function test_cross_organization_file_operations_are_rejected(): void
    {
        $manager = $this->manager();
        $uid = $manager->upload($this->organizationUid, 'private.txt', 'secret')['uid'];
        $other = Organization::createDefault('DE', 'de', 'EUR');
        (new OrganizationRepository($this->pdo))->save($other);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong to this organization');

        $manager->temporaryUrl($uid, new \DateTimeImmutable('+1 hour'), $other->uid->toString());
    }

    public function test_version_families_are_scoped_to_the_organization(): void
    {
        $manager = $this->manager();
        $other = Organization::createDefault('DE', 'de', 'EUR');
        (new OrganizationRepository($this->pdo))->save($other);

        $first = $manager->upload(
            $this->organizationUid,
            'contract.txt',
            'one',
            entityType: 'deal',
            entityUid: '01KYFM00000000000000000001',
        );
        $otherFirst = $manager->upload(
            $other->uid->toString(),
            'contract.txt',
            'two',
            entityType: 'deal',
            entityUid: '01KYFM00000000000000000001',
        );

        $this->assertSame(1, $first['versionNumber']);
        $this->assertSame(1, $otherFirst['versionNumber']);
        $this->assertNull($manager->find($first['uid'])['archived_at']);
    }

    public function test_ambiguous_storage_timeout_is_redacted_cleaned_and_retryable_without_duplicates(): void
    {
        $body = 'Confidential acquisition attachment';
        $credential = 'object-store-secret-key';
        $storage = new FailOnceAfterWriteStorage($credential);
        $events = new RecordingEventDispatcher();
        $manager = new FileManager(
            $storage,
            new FileRepository($this->pdo),
            new OrganizationRepository($this->pdo),
            $events,
        );

        try {
            $manager->upload($this->organizationUid, 'confidential.txt', $body);
            $this->fail('Expected the first storage attempt to time out.');
        } catch (\RuntimeException $error) {
            $this->assertSame('File storage write failed.', $error->getMessage());
            $this->assertStringNotContainsString($body, $error->getMessage());
            $this->assertStringNotContainsString($credential, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }

        $this->assertSame(1, $storage->putCalls);
        $this->assertCount(1, $storage->deletedPaths);
        $this->assertSame([], $storage->objects);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_files')->fetchColumn());
        $this->assertSame([], $events->dispatched);

        $uploaded = $manager->upload($this->organizationUid, 'confidential.txt', $body);

        $this->assertSame(2, $storage->putCalls);
        $this->assertCount(1, $storage->objects);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_files')->fetchColumn());
        $this->assertSame($uploaded['uid'], $manager->find($uploaded['uid'])['uid']);
        $this->assertCount(1, $events->eventsNamed('file.uploaded'));
    }

    public function test_metadata_failure_removes_the_object_and_a_corrected_retry_creates_one_file(): void
    {
        $storage = new RecordingStorage();
        $manager = new FileManager(
            $storage,
            new FileRepository($this->pdo),
            new OrganizationRepository($this->pdo),
        );
        $unsupportedMetadataValue = fopen('php://memory', 'rb');
        $this->assertIsResource($unsupportedMetadataValue);

        try {
            $manager->upload(
                $this->organizationUid,
                'report.txt',
                'report-body',
                metadata: ['unsupported' => $unsupportedMetadataValue],
            );
            $this->fail('Expected metadata serialization to fail.');
        } catch (\JsonException) {
            // Expected: the storage object must still be compensated below.
        } finally {
            fclose($unsupportedMetadataValue);
        }

        $this->assertCount(1, $storage->deletedPaths);
        $this->assertSame([], $storage->objects);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_files')->fetchColumn());

        $manager->upload(
            $this->organizationUid,
            'report.txt',
            'report-body',
            metadata: ['source' => 'retry'],
        );

        $this->assertSame(2, $storage->putCalls);
        $this->assertCount(1, $storage->objects);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_files')->fetchColumn());
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

class RecordingStorage implements StorageInterface
{
    public int $putCalls = 0;

    /** @var array<string, string> */
    public array $objects = [];

    /** @var string[] */
    public array $deletedPaths = [];

    public function put(string $path, mixed $contents, array $options = []): StoredFile
    {
        $this->putCalls++;
        $body = is_resource($contents) ? (string) stream_get_contents($contents) : (string) $contents;
        $this->objects[$path] = $body;

        return new StoredFile(
            path: $path,
            storage: 'fake-object-store',
            sizeBytes: strlen($body),
            checksum: hash('sha256', $body),
            mimeType: 'application/octet-stream',
        );
    }

    public function read(string $path)
    {
        if (!array_key_exists($path, $this->objects)) {
            throw new \RuntimeException('Object not found.');
        }

        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $this->objects[$path]);
        rewind($handle);

        return $handle;
    }

    public function delete(string $path): void
    {
        $this->deletedPaths[] = $path;
        unset($this->objects[$path]);
    }

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->objects);
    }

    public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string
    {
        return 'https://storage.invalid/' . rawurlencode($path);
    }
}

final class FailOnceAfterWriteStorage extends RecordingStorage
{
    public function __construct(private readonly string $credential)
    {
    }

    public function put(string $path, mixed $contents, array $options = []): StoredFile
    {
        $stored = parent::put($path, $contents, $options);

        if ($this->putCalls === 1) {
            throw new \RuntimeException(
                "Timeout after writing {$this->objects[$path]} to {$path} with {$this->credential}",
            );
        }

        return $stored;
    }
}
