<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Unit\Domain;

use Kontor\Reports\Domain\ScheduledReport;
use PHPUnit\Framework\TestCase;

final class ScheduledReportTest extends TestCase
{
    public function test_create_rejects_an_unsupported_recurrence_rule(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScheduledReport::create('org_01', 'crm_pipeline', 'Weekly pipeline', 'fortnightly');
    }

    public function test_advance_moves_next_run_at_forward_by_one_interval_from_itself(): void
    {
        $schedule = ScheduledReport::create(
            'org_01', 'crm_pipeline', 'Weekly pipeline', 'weekly', firstRunAt: new \DateTimeImmutable('2026-01-01 09:00:00'),
        );

        $schedule->advance();

        $this->assertEquals(new \DateTimeImmutable('2026-01-08 09:00:00'), $schedule->nextRunAt);
    }

    public function test_advance_is_relative_to_the_schedule_not_to_now_so_a_late_run_does_not_lose_its_slot(): void
    {
        $schedule = ScheduledReport::create(
            'org_01', 'crm_pipeline', 'Daily pipeline', 'daily', firstRunAt: new \DateTimeImmutable('2020-01-01 09:00:00'),
        );

        $schedule->advance();

        $this->assertEquals(new \DateTimeImmutable('2020-01-02 09:00:00'), $schedule->nextRunAt);
    }

    public function test_is_due_compares_against_the_given_instant(): void
    {
        $schedule = ScheduledReport::create('org_01', 'crm_pipeline', 'Weekly pipeline', 'weekly', firstRunAt: new \DateTimeImmutable('2026-01-01'));

        $this->assertTrue($schedule->isDue(new \DateTimeImmutable('2026-01-02')));
        $this->assertFalse($schedule->isDue(new \DateTimeImmutable('2025-12-31')));
    }
}
