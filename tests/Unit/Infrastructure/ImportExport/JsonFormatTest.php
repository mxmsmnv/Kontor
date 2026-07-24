<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\ImportExport;

use Kontor\Core\Infrastructure\ImportExport\Format\JsonRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonRecordWriter;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonlRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\JsonlRecordWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonFormatTest extends TestCase
{
    private string $jsonPath;
    private string $jsonlPath;

    protected function setUp(): void
    {
        $id = bin2hex(random_bytes(6));
        $this->jsonPath = sys_get_temp_dir() . "/kontor-json-{$id}.json";
        $this->jsonlPath = sys_get_temp_dir() . "/kontor-jsonl-{$id}.jsonl";
    }

    protected function tearDown(): void
    {
        foreach ([$this->jsonPath, $this->jsonlPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_json_round_trips_records(): void
    {
        $records = [
            ['name' => 'Acme', 'active' => true],
            ['name' => 'Widgets Inc', 'active' => false],
        ];

        (new JsonRecordWriter())->write($this->jsonPath, $records, ['name', 'active']);
        $result = iterator_to_array((new JsonRecordReader())->read($this->jsonPath));

        $this->assertSame($records, $result);
    }

    public function test_json_reader_rejects_a_file_that_is_not_a_top_level_array(): void
    {
        file_put_contents($this->jsonPath, json_encode(['not' => 'an array']));

        $this->expectException(RuntimeException::class);

        iterator_to_array((new JsonRecordReader())->read($this->jsonPath));
    }

    public function test_jsonl_round_trips_records(): void
    {
        $records = [
            ['name' => 'Acme', 'active' => true],
            ['name' => 'Widgets Inc', 'active' => false],
        ];

        (new JsonlRecordWriter())->write($this->jsonlPath, $records, ['name', 'active']);
        $result = iterator_to_array((new JsonlRecordReader())->read($this->jsonlPath));

        $this->assertSame($records, $result);
    }

    public function test_jsonl_skips_blank_lines(): void
    {
        file_put_contents($this->jsonlPath, "{\"name\":\"Acme\"}\n\n{\"name\":\"Widgets\"}\n");

        $result = iterator_to_array((new JsonlRecordReader())->read($this->jsonlPath));

        $this->assertCount(2, $result);
    }
}
