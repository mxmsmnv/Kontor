<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\SDK\DTO\ReportResult;

/**
 * The "exports" milestone — kontor.md#29 lists "Excel/CSV/JSON/PDF
 * export". CSV/JSON/XLSX reuse Kontor\Core\Infrastructure\ImportExport's
 * existing format writers (Substage 1.5) directly — they already operate
 * on a plain iterable of associative arrays plus a field list, exactly
 * ReportResult::$rows' shape, so there's nothing report-specific to write
 * for those three. PDF reuses kontor/documents' PdfRenderer against a
 * minimal generated HTML table rather than going through the template
 * engine — a report export isn't an "issued document" with a template a
 * user would customize, just tabular data in a fourth format.
 */
final class ReportExportService
{
    public function __construct(
        private readonly FormatResolver $formats,
        private readonly PdfRenderer $pdf,
    ) {
    }

    /**
     * @param string[] $fields
     */
    public function export(ReportResult $result, array $fields, string $format, string $path): void
    {
        if (strtolower($format) === 'pdf') {
            file_put_contents($path, $this->pdf->render($this->htmlTable($result, $fields)));

            return;
        }

        $this->formats->writer($format)->write($path, $result->rows, $fields);
    }

    /**
     * @param string[] $fields
     */
    private function htmlTable(ReportResult $result, array $fields): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $header = '<tr>' . implode('', array_map(static fn (string $f): string => '<th>' . $escape($f) . '</th>', $fields)) . '</tr>';

        $body = '';
        foreach ($result->rows as $row) {
            $body .= '<tr>' . implode('', array_map(
                static fn (string $f): string => '<td>' . $escape($row[$f] ?? '') . '</td>',
                $fields,
            )) . '</tr>';
        }

        return "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\"><thead>{$header}</thead><tbody>{$body}</tbody></table>";
    }
}
