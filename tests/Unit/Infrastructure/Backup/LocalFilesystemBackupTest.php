<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Backup;

use Kontor\Core\Infrastructure\Backup\Local\LocalFilesystemBackupReader;
use Kontor\Core\Infrastructure\Backup\Local\LocalFilesystemBackupWriter;
use PHPUnit\Framework\TestCase;

final class LocalFilesystemBackupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kontor-backup-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_writes_and_reads_a_table_round_trip(): void
    {
        $writer = new LocalFilesystemBackupWriter($this->root);
        $writer->writeTable('kontor_organizations', [
            ['id' => 1, 'name' => 'Acme'],
            ['id' => 2, 'name' => 'Widgets Inc'],
        ]);

        $reader = new LocalFilesystemBackupReader($this->root);
        $rows = iterator_to_array($reader->readTable('kontor_organizations'));

        $this->assertSame([
            ['id' => 1, 'name' => 'Acme'],
            ['id' => 2, 'name' => 'Widgets Inc'],
        ], $rows);
    }

    public function test_reading_an_unwritten_table_yields_nothing(): void
    {
        new LocalFilesystemBackupWriter($this->root);
        $reader = new LocalFilesystemBackupReader($this->root);

        $this->assertSame([], iterator_to_array($reader->readTable('kontor_nonexistent')));
        $this->assertFalse($reader->hasTable('kontor_nonexistent'));
    }

    public function test_metadata_round_trips(): void
    {
        $writer = new LocalFilesystemBackupWriter($this->root);
        $writer->writeMetadata(['component' => 'core', 'rowCounts' => ['kontor_organizations' => 2]]);

        $reader = new LocalFilesystemBackupReader($this->root);

        $this->assertSame(['component' => 'core', 'rowCounts' => ['kontor_organizations' => 2]], $reader->readMetadata());
    }

    public function test_files_round_trip(): void
    {
        $writer = new LocalFilesystemBackupWriter($this->root);
        $writer->writeFile('logo.png', 'binary-content');

        $reader = new LocalFilesystemBackupReader($this->root);
        $handle = $reader->readFile('logo.png');

        $this->assertSame('binary-content', stream_get_contents($handle));
        fclose($handle);
    }

    public function test_reader_throws_for_a_missing_backup_directory(): void
    {
        $this->expectException(\RuntimeException::class);

        new LocalFilesystemBackupReader($this->root . '/does-not-exist');
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
