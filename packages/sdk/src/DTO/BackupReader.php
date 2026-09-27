<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

/**
 * Streaming source a BackupProviderInterface reads a previously written
 * backup from, for verification or restore (kontor.md#24).
 */
interface BackupReader
{
    public function readTable(string $table): iterable;

    public function readFile(string $path);

    public function readMetadata(): array;

    public function hasTable(string $table): bool;
}
