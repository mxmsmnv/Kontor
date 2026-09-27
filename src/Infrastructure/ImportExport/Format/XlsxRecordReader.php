<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx file. Handles both shared strings
 * (xl/sharedStrings.xml, the default Excel/LibreOffice writes) and inline
 * strings, so files produced by real spreadsheet software — not just
 * XlsxRecordWriter — read correctly. Empty trailing cells are legitimately
 * omitted from the XML, so cells are placed by their `r` reference
 * (e.g. "C5") rather than by sequential position.
 */
final class XlsxRecordReader implements RecordReaderInterface
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function read(string $path): iterable
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Could not open XLSX file \"{$path}\".");
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $this->readFirstSheetXml($zip);
        $zip->close();

        if ($sheetXml === null) {
            throw new RuntimeException("XLSX file \"{$path}\" does not contain a worksheet.");
        }

        $dom = new DOMDocument();
        $dom->loadXML($sheetXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::MAIN_NS);

        $header = null;

        foreach ($xpath->query('//s:row') as $rowElement) {
            /** @var DOMElement $rowElement */
            $rowValues = $this->parseRow($rowElement, $sharedStrings);

            if ($header === null) {
                $header = $rowValues;

                continue;
            }

            $record = [];

            foreach ($header as $columnIndex => $columnName) {
                if ($columnName === null || $columnName === '') {
                    continue;
                }

                $record[$columnName] = $rowValues[$columnIndex] ?? null;
            }

            yield $record;
        }
    }

    /**
     * @return string[]
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::MAIN_NS);

        $strings = [];

        foreach ($xpath->query('//s:si') as $si) {
            /** @var DOMElement $si */
            $strings[] = $this->innerText($si);
        }

        return $strings;
    }

    private function readFirstSheetXml(ZipArchive $zip): ?string
    {
        $direct = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($direct !== false) {
            return $direct;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                $xml = $zip->getFromIndex($i);

                return $xml !== false ? $xml : null;
            }
        }

        return null;
    }

    /**
     * @param string[] $sharedStrings
     * @return array<int, mixed>
     */
    private function parseRow(DOMElement $rowElement, array $sharedStrings): array
    {
        $values = [];

        foreach ($rowElement->getElementsByTagName('c') as $cell) {
            /** @var DOMElement $cell */
            $columnIndex = self::columnIndexFromRef($cell->getAttribute('r'));
            $type = $cell->getAttribute('t');

            $vNodes = $cell->getElementsByTagName('v');
            $rawValue = $vNodes->length > 0 ? $vNodes->item(0)->textContent : null;

            $values[$columnIndex] = match ($type) {
                's' => $sharedStrings[(int) $rawValue] ?? '',
                'inlineStr' => $this->innerText($cell),
                'b' => $rawValue === '1',
                default => $rawValue,
            };
        }

        if ($values === []) {
            return [];
        }

        $max = max(array_keys($values));
        $result = [];

        for ($i = 0; $i <= $max; $i++) {
            $result[$i] = $values[$i] ?? null;
        }

        return $result;
    }

    private function innerText(DOMElement $element): string
    {
        $value = '';

        foreach ($element->getElementsByTagName('t') as $t) {
            $value .= $t->textContent;
        }

        return $value;
    }

    private static function columnIndexFromRef(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $matches);
        $letters = $matches[1] ?? 'A';

        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }
}
