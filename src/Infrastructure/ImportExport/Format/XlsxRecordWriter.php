<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use RuntimeException;
use ZipArchive;

/**
 * Writes a minimal, valid single-sheet .xlsx: no styles, no shared string
 * table (every text cell is inline), just data. This is a data-exchange
 * format writer, not a spreadsheet design tool — formatting, formulas and
 * multiple sheets are out of scope.
 */
final class XlsxRecordWriter implements RecordWriterInterface
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const OFFICE_DOCUMENT_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function write(string $path, iterable $records, array $fields): void
    {
        if (is_file($path)) {
            unlink($path);
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Could not create XLSX file \"{$path}\".");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->packageRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($records, $fields));

        $zip->close();
    }

    /**
     * @param iterable<array<string, mixed>> $records
     * @param string[] $fields
     */
    private function sheetXml(iterable $records, array $fields): string
    {
        $rows = [$this->rowXml(1, array_values($fields))];
        $rowNumber = 2;

        foreach ($records as $record) {
            $values = array_map(static fn (string $field): mixed => $record[$field] ?? '', $fields);
            $rows[] = $this->rowXml($rowNumber, $values);
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="' . self::MAIN_NS . '">'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * @param mixed[] $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        $cells = [];

        foreach (array_values($values) as $columnIndex => $value) {
            $ref = self::columnLetter($columnIndex) . $rowNumber;

            $cells[] = is_int($value) || is_float($value)
                ? "<c r=\"{$ref}\"><v>" . self::escape((string) $value) . '</v></c>'
                : "<c r=\"{$ref}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::escape((string) $value) . '</t></is></c>';
        }

        return "<row r=\"{$rowNumber}\">" . implode('', $cells) . '</row>';
    }

    private static function columnLetter(int $zeroBasedIndex): string
    {
        $index = $zeroBasedIndex + 1;
        $letters = '';

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function packageRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="' . self::RELATIONSHIPS_NS . '">'
            . '<Relationship Id="rId1" Type="' . self::OFFICE_DOCUMENT_NS . '/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="' . self::MAIN_NS . '" xmlns:r="' . self::OFFICE_DOCUMENT_NS . '">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="' . self::RELATIONSHIPS_NS . '">'
            . '<Relationship Id="rId1" Type="' . self::OFFICE_DOCUMENT_NS . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }
}
