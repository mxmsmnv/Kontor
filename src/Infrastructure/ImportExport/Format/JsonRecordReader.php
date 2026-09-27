<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;

/**
 * Reads a JSON file containing a top-level array of record objects. Loads
 * the whole file at once — for large, streaming-friendly exchanges prefer
 * JsonlRecordReader.
 */
final class JsonRecordReader implements RecordReaderInterface
{
    public function read(string $path): iterable
    {
        if (!is_file($path)) {
            throw new RuntimeException("JSON file \"{$path}\" was not found.");
        }

        $data = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data) || !array_is_list($data)) {
            throw new RuntimeException("JSON file \"{$path}\" must contain a top-level array of records.");
        }

        foreach ($data as $record) {
            yield is_array($record) ? $record : [];
        }
    }
}
