<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;

final class CsvRecordWriter implements RecordWriterInterface
{
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
    ) {
    }

    public function write(string $path, iterable $records, array $fields): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not write CSV file \"{$path}\".");
        }

        try {
            fputcsv($handle, $fields, $this->delimiter, $this->enclosure, $this->escape);

            foreach ($records as $record) {
                fputcsv(
                    $handle,
                    array_map(static fn (string $field): mixed => $record[$field] ?? '', $fields),
                    $this->delimiter,
                    $this->enclosure,
                    $this->escape
                );
            }
        } finally {
            fclose($handle);
        }
    }
}
