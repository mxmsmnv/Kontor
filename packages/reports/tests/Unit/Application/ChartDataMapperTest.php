<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Unit\Application;

use Kontor\Reports\Application\ChartDataMapper;
use Kontor\SDK\DTO\ReportResult;
use PHPUnit\Framework\TestCase;

final class ChartDataMapperTest extends TestCase
{
    public function test_to_series_extracts_labels_and_values(): void
    {
        $result = new ReportResult(rows: [
            ['stage_uid' => 'lead', 'deal_count' => 4],
            ['stage_uid' => 'won', 'deal_count' => 2],
        ]);

        $series = (new ChartDataMapper())->toSeries($result, 'stage_uid', 'deal_count');

        $this->assertSame(['lead', 'won'], $series['labels']);
        $this->assertSame([4, 2], $series['data']);
    }

    public function test_to_series_defaults_missing_or_non_numeric_values_to_zero(): void
    {
        $result = new ReportResult(rows: [['stage_uid' => 'lead']]);

        $series = (new ChartDataMapper())->toSeries($result, 'stage_uid', 'deal_count');

        $this->assertSame([0], $series['data']);
    }

    public function test_to_multi_series_shares_one_label_set_across_value_fields(): void
    {
        $result = new ReportResult(rows: [
            ['stage_uid' => 'lead', 'deal_count' => 4, 'total_value_minor' => 10000],
            ['stage_uid' => 'won', 'deal_count' => 2, 'total_value_minor' => 5000],
        ]);

        $series = (new ChartDataMapper())->toMultiSeries($result, 'stage_uid', ['deal_count', 'total_value_minor']);

        $this->assertSame(['lead', 'won'], $series['labels']);
        $this->assertSame([4, 2], $series['series']['deal_count']);
        $this->assertSame([10000, 5000], $series['series']['total_value_minor']);
    }
}
