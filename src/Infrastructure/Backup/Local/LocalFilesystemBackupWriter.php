<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Backup\Local;

use Kontor\SDK\DTO\BackupWriter;
use RuntimeException;

/**
 * Writes a backup to local protected storage (kontor.md#24) as one JSON
 * Lines file per table plus a metadata.json — the first supported backup
 * destination; cloud adapters (S3, R2, B2, Google Drive, SFTP) are separate
 * integration components (kontor.md#5.5) that would provide the same
 * BackupWriter contract over a different transport.
 */
final class LocalFilesystemBackupWriter implements BackupWriter
{
    public function __construct(private readonly string $rootDir)
    {
        if (!is_dir($rootDir) && !mkdir($rootDir, 0770, true) && !is_dir($rootDir)) {
            throw new RuntimeException("Could not create backup directory \"{$rootDir}\".");
        }
    }

    public function writeTable(string $table, iterable $rows): void
    {
        $path = $this->tablesDir() . DIRECTORY_SEPARATOR . $table . '.jsonl';

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not write backup table file \"{$path}\".");
        }

        try {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
            }
        } finally {
            fclose($handle);
        }
    }

    public function writeFile(string $path, mixed $contents): void
    {
        $target = $this->filesDir() . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        $directory = dirname($target);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory \"{$directory}\".");
        }

        $data = is_resource($contents) ? stream_get_contents($contents) : (string) $contents;
        file_put_contents($target, $data);
    }

    public function writeMetadata(array $metadata): void
    {
        file_put_contents(
            $this->rootDir . DIRECTORY_SEPARATOR . 'metadata.json',
            json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    private function tablesDir(): string
    {
        return $this->ensureDir($this->rootDir . DIRECTORY_SEPARATOR . 'tables');
    }

    private function filesDir(): string
    {
        return $this->ensureDir($this->rootDir . DIRECTORY_SEPARATOR . 'files');
    }

    private function ensureDir(string $dir): string
    {
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create directory \"{$dir}\".");
        }

        return $dir;
    }
}
