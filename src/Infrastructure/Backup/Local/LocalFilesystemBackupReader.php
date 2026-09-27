<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Backup\Local;

use Kontor\SDK\DTO\BackupReader;
use RuntimeException;

final class LocalFilesystemBackupReader implements BackupReader
{
    public function __construct(private readonly string $rootDir)
    {
        if (!is_dir($rootDir)) {
            throw new RuntimeException("No backup found at \"{$rootDir}\".");
        }
    }

    public function readTable(string $table): iterable
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'tables' . DIRECTORY_SEPARATOR . $table . '.jsonl';

        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not read backup table file \"{$path}\".");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                yield json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
            }
        } finally {
            fclose($handle);
        }
    }

    public function readFile(string $path)
    {
        $target = $this->rootDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        if (!is_file($target)) {
            throw new RuntimeException("File \"{$path}\" was not found in this backup.");
        }

        return fopen($target, 'rb');
    }

    public function readMetadata(): array
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'metadata.json';

        if (!is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR) ?? [];
    }

    public function hasTable(string $table): bool
    {
        return is_file($this->rootDir . DIRECTORY_SEPARATOR . 'tables' . DIRECTORY_SEPARATOR . $table . '.jsonl');
    }
}
