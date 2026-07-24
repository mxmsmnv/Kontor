<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

/**
 * Writes a stream of records to a file in a given column order
 * (kontor.md#25 required export formats).
 */
interface RecordWriterInterface
{
    /**
     * @param iterable<array<string, mixed>> $records
     * @param string[] $fields column order and header names
     */
    public function write(string $path, iterable $records, array $fields): void;
}
