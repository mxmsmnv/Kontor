<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

/**
 * Streaming sink a BackupProviderInterface::export() writes into.
 * Concrete implementations decide the on-disk/on-wire format (kontor.md#24).
 */
interface BackupWriter
{
    public function writeTable(string $table, iterable $rows): void;

    public function writeFile(string $path, mixed $contents): void;

    public function writeMetadata(array $metadata): void;
}
