<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

final class JsonRecordWriter implements RecordWriterInterface
{
    public function write(string $path, iterable $records, array $fields): void
    {
        $out = [];

        foreach ($records as $record) {
            $row = [];

            foreach ($fields as $field) {
                $row[$field] = $record[$field] ?? null;
            }

            $out[] = $row;
        }

        file_put_contents($path, json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
