<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;

/**
 * Reads JSON Lines (one record object per line) — the streaming-friendly
 * format for large exchanges (kontor.md#25 "large streaming exports").
 */
final class JsonlRecordReader implements RecordReaderInterface
{
    public function read(string $path): iterable
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open JSONL file \"{$path}\".");
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
}
