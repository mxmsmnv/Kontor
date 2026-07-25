<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use Kontor\SDK\DTO\ReportResult;

/**
 * The "charts" milestone: turns a ReportResult's rows into chart-ready
 * label/value series. No charting UI/library is built (no admin UI is
 * built anywhere in this monorepo yet) — this is the data shape a future
 * chart component would consume directly.
 */
final class ChartDataMapper
{
    /**
     * @return array{labels: array<int, string>, data: array<int, int|float>}
     */
    public function toSeries(ReportResult $result, string $labelField, string $valueField): array
    {
        $labels = [];
        $data = [];

        foreach ($result->rows as $row) {
            $labels[] = (string) ($row[$labelField] ?? '');
            $data[] = is_numeric($row[$valueField] ?? null) ? $row[$valueField] : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Multiple value fields sharing one set of labels — a grouped/stacked
     * bar chart's shape.
     *
     * @param string[] $valueFields
     * @return array{labels: array<int, string>, series: array<string, array<int, int|float>>}
     */
    public function toMultiSeries(ReportResult $result, string $labelField, array $valueFields): array
    {
        $labels = [];
        $series = array_fill_keys($valueFields, []);

        foreach ($result->rows as $row) {
            $labels[] = (string) ($row[$labelField] ?? '');

            foreach ($valueFields as $field) {
                $series[$field][] = is_numeric($row[$field] ?? null) ? $row[$field] : 0;
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }
}
