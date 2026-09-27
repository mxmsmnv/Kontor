<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\ImportExport;

use Kontor\Core\Infrastructure\ImportExport\Format\XlsxRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\XlsxRecordWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class XlsxFormatTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-xlsx-' . bin2hex(random_bytes(6)) . '.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_round_trips_records_written_by_our_own_writer(): void
    {
        $records = [
            ['name' => 'Acme', 'employees' => 12],
            ['name' => 'Widgets & Co', 'employees' => 340],
        ];

        (new XlsxRecordWriter())->write($this->path, $records, ['name', 'employees']);
        $result = iterator_to_array((new XlsxRecordReader())->read($this->path));

        $this->assertSame('Acme', $result[0]['name']);
        $this->assertSame('12', $result[0]['employees']);
        $this->assertSame('Widgets & Co', $result[1]['name']);
        $this->assertSame('340', $result[1]['employees']);
    }

    public function test_reads_a_file_using_shared_strings_like_real_spreadsheet_software(): void
    {
        $this->buildSharedStringsXlsx($this->path);

        $result = iterator_to_array((new XlsxRecordReader())->read($this->path));

        $this->assertSame([
            ['name' => 'Acme', 'employees' => '12'],
        ], $result);
    }

    public function test_omitted_trailing_empty_cells_are_filled_as_null(): void
    {
        $this->buildSparseRowXlsx($this->path);

        $result = iterator_to_array((new XlsxRecordReader())->read($this->path));

        $this->assertSame(['a' => '1', 'b' => null, 'c' => '3'], $result[0]);
    }

    /**
     * Builds an .xlsx the way real spreadsheet software does: header and
     * data strings referenced from a shared string table (t="s"), not
     * inline — this is what our own writer does NOT produce, so it proves
     * the reader is compatible with third-party files, not just its own.
     */
    private function buildSharedStringsXlsx(string $path): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        // shared string table: 0 => "name", 1 => "employees", 2 => "Acme"
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">'
            . '<si><t>name</t></si><si><t>employees</t></si><si><t>Acme</t></si>'
            . '</sst>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>12</v></c></row>'
            . '</sheetData>'
            . '</worksheet>');

        $zip->close();
    }

    /**
     * Row with three header columns (a, b, c) but the data row's middle
     * cell (b) is entirely omitted from the XML, as real writers do for
     * empty cells.
     */
    private function buildSparseRowXlsx(string $path): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>'
            . '<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c><c r="B1" t="inlineStr"><is><t>b</t></is></c><c r="C1" t="inlineStr"><is><t>c</t></is></c></row>'
            . '<row r="2"><c r="A2"><v>1</v></c><c r="C2"><v>3</v></c></row>'
            . '</sheetData>'
            . '</worksheet>');

        $zip->close();
    }
}
