<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\BackupManager;
use Kontor\Core\Infrastructure\Backup\BackupArchiveBuilder;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\SDK\Contracts\BackupProviderInterface;
use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\BackupEstimate;
use Kontor\SDK\DTO\BackupReader;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\BackupWriter;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;
use PHPUnit\Framework\TestCase;

final class BackupManagerTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/kontor-backup-manager-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storageDir);
    }

    public function test_create_exports_and_verifies_using_the_real_local_writer_and_reader(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 2));

        $manager = new BackupManager($registry, $this->storageDir);
        $record = $manager->create('demo', 'component', 'org_01');

        $this->assertTrue($record->verified);
        $this->assertSame(2, $record->itemCount);
        $this->assertDirectoryExists($record->path);
        $this->assertFileExists($record->path . '/tables/items.jsonl');
    }

    public function test_create_surfaces_a_failed_verification_instead_of_throwing(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 99));

        $manager = new BackupManager($registry, $this->storageDir);
        $record = $manager->create('demo', 'component', 'org_01');

        $this->assertFalse($record->verified);
        $this->assertNotEmpty($record->verificationErrors);
    }

    public function test_restore_delegates_to_the_provider_with_a_reader_over_the_backup_path(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 2));

        $manager = new BackupManager($registry, $this->storageDir);
        $record = $manager->create('demo', 'component', 'org_01');

        $result = $manager->restore($record->path, 'demo', 'org_01', dryRun: true);

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->restoredCount);
    }

    public function test_list_returns_every_backup_directory(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 2));

        $manager = new BackupManager($registry, $this->storageDir);
        $manager->create('demo', 'component', 'org_01');
        $manager->create('demo', 'component', 'org_01');

        $this->assertCount(2, $manager->list());
    }

    public function test_list_returns_empty_when_nothing_backed_up_yet(): void
    {
        $manager = new BackupManager(new BackupProviderRegistry(), $this->storageDir);

        $this->assertSame([], $manager->list());
    }

    public function test_find_by_id_resolves_only_exact_backup_directory_names(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 2));
        $manager = new BackupManager($registry, $this->storageDir);
        $record = $manager->create('demo', 'component', 'org_01');

        $this->assertSame($record->path, $manager->findById(basename($record->path)));
        $this->assertNull($manager->findById('../' . basename($record->path)));
        $this->assertNull($manager->findById('missing-backup'));
    }

    public function test_archive_builder_packages_backup_with_relative_paths_only(): void
    {
        $registry = new BackupProviderRegistry();
        $registry->register('demo', new RowCountingBackupProvider(expectedItems: 2));
        $record = (new BackupManager($registry, $this->storageDir))
            ->create('demo', 'component', 'org_01');
        $archivePath = $this->storageDir . '/snapshot.zip';

        (new BackupArchiveBuilder())->create($record->path, $archivePath);

        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($archivePath));
        $this->assertNotFalse($archive->locateName('metadata.json'));
        $this->assertNotFalse($archive->locateName('tables/items.jsonl'));
        $this->assertFalse($archive->locateName($record->path . '/metadata.json'));
        $archive->close();
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

/**
 * Exports a fixed "items" table with 2 rows, and considers a backup
 * verified only if it contains exactly $expectedItems rows — lets tests
 * exercise both a passing and a failing verification through the real
 * local filesystem writer/reader.
 */
final class RowCountingBackupProvider implements BackupProviderInterface
{
    public function __construct(private readonly int $expectedItems)
    {
    }

    public function component(): string
    {
        return 'demo';
    }

    public function estimate(BackupContext $context): BackupEstimate
    {
        return new BackupEstimate(itemCount: 2, estimatedSizeBytes: 1024);
    }

    public function export(BackupWriter $writer, BackupContext $context): void
    {
        $writer->writeTable('items', [['id' => 1], ['id' => 2]]);
        $writer->writeMetadata(['rowCounts' => ['items' => 2]]);
    }

    public function verify(BackupReader $reader, BackupContext $context): BackupVerification
    {
        $count = iterator_count($reader->readTable('items'));

        return $count === $this->expectedItems
            ? new BackupVerification(verified: true)
            : new BackupVerification(verified: false, errors: ["expected {$this->expectedItems} items, found {$count}"]);
    }

    public function restore(BackupReader $reader, RestoreContext $context): RestoreResult
    {
        return new RestoreResult(success: true, restoredCount: iterator_count($reader->readTable('items')));
    }
}
