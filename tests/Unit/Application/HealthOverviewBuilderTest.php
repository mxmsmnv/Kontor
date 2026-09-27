<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\SDK\DTO\HealthCheckResult;
use PHPUnit\Framework\TestCase;

final class HealthOverviewBuilderTest extends TestCase
{
    public function test_summarizes_all_checks_while_filtering_visible_results(): void
    {
        $overview = (new HealthOverviewBuilder())->build([
            ['key' => 'core', 'result' => new HealthCheckResult('ok', 'Database ready')],
            ['key' => 'queue', 'result' => new HealthCheckResult(
                'warning',
                'Dead-letter jobs found',
                ['deadLetterCount' => 2]
            )],
            ['key' => 'search', 'result' => new HealthCheckResult('critical', 'Index unavailable')],
        ], query: 'deadlettercount', status: 'warning');

        $this->assertSame(['ok' => 1, 'warning' => 1, 'critical' => 1], $overview['counts']);
        $this->assertSame('critical', $overview['overall']);
        $this->assertCount(1, $overview['checks']);
        $this->assertSame('queue', $overview['checks'][0]['key']);
    }

    public function test_reports_ok_when_every_check_is_healthy(): void
    {
        $overview = (new HealthOverviewBuilder())->build([
            ['key' => 'core', 'result' => new HealthCheckResult('ok')],
            ['key' => 'queue', 'result' => new HealthCheckResult('ok')],
        ]);

        $this->assertSame('ok', $overview['overall']);
        $this->assertCount(2, $overview['checks']);
    }
}
