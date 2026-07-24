<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport;

use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonlRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonlRecordWriter;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonRecordWriter;
use Kontor\Core\Infrastructure\ImportExport\Format\RecordReaderInterface;
use Kontor\Core\Infrastructure\ImportExport\Format\RecordWriterInterface;
use Kontor\Core\Infrastructure\ImportExport\Format\XlsxRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\XlsxRecordWriter;
use InvalidArgumentException;

/**
 * Resolves a format key ("csv", "json", "jsonl", "xlsx") to the matching
 * reader/writer (kontor.md#25 required import/export formats).
 */
final class FormatResolver
{
    public function reader(string $format): RecordReaderInterface
    {
        return match (strtolower($format)) {
            'csv' => new CsvRecordReader(),
            'json' => new JsonRecordReader(),
            'jsonl' => new JsonlRecordReader(),
            'xlsx' => new XlsxRecordReader(),
            default => throw new InvalidArgumentException("Unsupported import format \"{$format}\"."),
        };
    }

    public function writer(string $format): RecordWriterInterface
    {
        return match (strtolower($format)) {
            'csv' => new CsvRecordWriter(),
            'json' => new JsonRecordWriter(),
            'jsonl' => new JsonlRecordWriter(),
            'xlsx' => new XlsxRecordWriter(),
            default => throw new InvalidArgumentException("Unsupported export format \"{$format}\"."),
        };
    }

    public function detectFormat(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => 'csv',
            'json' => 'json',
            'jsonl', 'ndjson' => 'jsonl',
            'xlsx' => 'xlsx',
            default => throw new InvalidArgumentException("Could not detect a supported format from \"{$path}\"."),
        };
    }
}
