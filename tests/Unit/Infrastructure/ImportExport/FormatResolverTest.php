<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\ImportExport;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonlRecordWriter;
use Kontor\Core\Infrastructure\ImportExport\Format\XlsxRecordReader;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use PHPUnit\Framework\TestCase;

final class FormatResolverTest extends TestCase
{
    public function test_resolves_a_reader_by_format_key(): void
    {
        $resolver = new FormatResolver();

        $this->assertInstanceOf(CsvRecordReader::class, $resolver->reader('csv'));
        $this->assertInstanceOf(XlsxRecordReader::class, $resolver->reader('XLSX'));
    }

    public function test_resolves_a_writer_by_format_key(): void
    {
        $this->assertInstanceOf(JsonlRecordWriter::class, (new FormatResolver())->writer('jsonl'));
    }

    public function test_unsupported_format_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FormatResolver())->reader('pdf');
    }

    public function test_detect_format_from_file_extension(): void
    {
        $resolver = new FormatResolver();

        $this->assertSame('csv', $resolver->detectFormat('/tmp/contacts.csv'));
        $this->assertSame('xlsx', $resolver->detectFormat('/tmp/contacts.XLSX'));
        $this->assertSame('jsonl', $resolver->detectFormat('/tmp/contacts.ndjson'));
    }

    public function test_detect_format_throws_for_unknown_extension(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FormatResolver())->detectFormat('/tmp/contacts.txt');
    }
}
