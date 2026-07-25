<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Unit\Domain;

use Kontor\Projects\Domain\TimeEntry;
use PHPUnit\Framework\TestCase;

final class TimeEntryTest extends TestCase
{
    public function test_start_creates_a_running_entry(): void
    {
        $entry = TimeEntry::start('org_01', 'proj_01', 5);

        $this->assertTrue($entry->isRunning());
        $this->assertNull($entry->durationMinutes);
    }

    public function test_close_computes_duration_minutes(): void
    {
        $entry = TimeEntry::start('org_01', 'proj_01', 5);

        $entry->close($entry->startedAt->modify('+90 minutes'));

        $this->assertFalse($entry->isRunning());
        $this->assertSame(90, $entry->durationMinutes);
        $this->assertSame(1.5, $entry->durationHours());
    }

    public function test_close_rejects_an_end_before_the_start(): void
    {
        $entry = TimeEntry::start('org_01', 'proj_01', 5);

        $this->expectException(\InvalidArgumentException::class);
        $entry->close($entry->startedAt->modify('-1 minute'));
    }

    public function test_log_manual_closes_immediately(): void
    {
        $start = new \DateTimeImmutable('2026-01-01 09:00:00');
        $end = new \DateTimeImmutable('2026-01-01 11:30:00');

        $entry = TimeEntry::logManual('org_01', 'proj_01', 5, $start, $end);

        $this->assertFalse($entry->isRunning());
        $this->assertSame(150, $entry->durationMinutes);
    }

    public function test_is_invoiced_reflects_the_invoice_line_reference(): void
    {
        $entry = TimeEntry::start('org_01', 'proj_01', 5);
        $this->assertFalse($entry->isInvoiced());

        $entry->invoiceLineUid = 'line_01';
        $this->assertTrue($entry->isInvoiced());
    }
}
