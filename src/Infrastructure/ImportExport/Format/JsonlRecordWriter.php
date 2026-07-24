<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;

final class JsonlRecordWriter implements RecordWriterInterface
{
    public function write(string $path, iterable $records, array $fields): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not write JSONL file \"{$path}\".");
        }

        try {
            foreach ($records as $record) {
                $row = [];

                foreach ($fields as $field) {
                    $row[$field] = $record[$field] ?? null;
                }

                fwrite($handle, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
            }
        } finally {
            fclose($handle);
        }
    }
}
