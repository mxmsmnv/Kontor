<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\ImportExport;

use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;
use PHPUnit\Framework\TestCase;

final class CsvFormatTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-csv-' . bin2hex(random_bytes(6)) . '.csv';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_round_trips_records(): void
    {
        $records = [
            ['name' => 'Acme', 'email' => 'info@acme.test'],
            ['name' => 'Widgets, Inc.', 'email' => 'hello@widgets.test'],
        ];

        (new CsvRecordWriter())->write($this->path, $records, ['name', 'email']);
        $result = iterator_to_array((new CsvRecordReader())->read($this->path));

        $this->assertSame($records, $result);
    }

    public function test_missing_fields_become_empty_strings_on_write_and_read(): void
    {
        (new CsvRecordWriter())->write($this->path, [['name' => 'Acme']], ['name', 'email']);
        $result = iterator_to_array((new CsvRecordReader())->read($this->path));

        $this->assertSame(['name' => 'Acme', 'email' => ''], $result[0]);
    }

    public function test_reading_a_header_only_file_yields_no_records(): void
    {
        (new CsvRecordWriter())->write($this->path, [], ['name', 'email']);

        $this->assertSame([], iterator_to_array((new CsvRecordReader())->read($this->path)));
    }
}
