<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;

final class CsvRecordReader implements RecordReaderInterface
{
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
    ) {
    }

    public function read(string $path): iterable
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open CSV file \"{$path}\".");
        }

        try {
            $header = fgetcsv($handle, null, $this->delimiter, $this->enclosure, $this->escape);

            if ($header === false) {
                return;
            }

            $columnCount = count($header);

            while (($row = fgetcsv($handle, null, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $row = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);

                yield array_combine($header, $row);
            }
        } finally {
            fclose($handle);
        }
    }
}
