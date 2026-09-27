<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Unit\Domain;

use Kontor\Tasks\Domain\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function test_create_rejects_an_unsupported_recurrence_rule(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Task::create('org_01', 'Follow up', recurrenceRule: 'fortnightly');
    }

    public function test_non_recurring_task_has_no_next_occurrence(): void
    {
        $task = Task::create('org_01', 'One-off task');

        $this->assertNull($task->nextOccurrenceDueAt());
        $this->assertFalse($task->isRecurring());
    }

    public function test_daily_recurrence_advances_due_date_by_one_day(): void
    {
        $dueAt = new \DateTimeImmutable('2026-01-01 09:00:00');
        $task = Task::create('org_01', 'Daily standup', dueAt: $dueAt, recurrenceRule: 'daily');

        $this->assertEquals(new \DateTimeImmutable('2026-01-02 09:00:00'), $task->nextOccurrenceDueAt());
    }

    public function test_monthly_recurrence_advances_by_one_month(): void
    {
        $dueAt = new \DateTimeImmutable('2026-01-15');
        $task = Task::create('org_01', 'Monthly report', dueAt: $dueAt, recurrenceRule: 'monthly');

        $this->assertEquals(new \DateTimeImmutable('2026-02-15'), $task->nextOccurrenceDueAt());
    }

    public function test_recurrence_until_stops_generating_beyond_the_cutoff(): void
    {
        $task = Task::create(
            'org_01', 'Weekly sync',
            dueAt: new \DateTimeImmutable('2026-01-01'),
            recurrenceRule: 'weekly',
            recurrenceUntil: new \DateTimeImmutable('2026-01-05'),
        );

        $this->assertNull($task->nextOccurrenceDueAt());
    }

    public function test_is_overdue_only_when_open_with_a_past_due_date(): void
    {
        $overdue = Task::create('org_01', 'Late task', dueAt: new \DateTimeImmutable('-1 day'));
        $this->assertTrue($overdue->isOverdue());

        $future = Task::create('org_01', 'Future task', dueAt: new \DateTimeImmutable('+1 day'));
        $this->assertFalse($future->isOverdue());

        $doneButLate = Task::create('org_01', 'Done task', dueAt: new \DateTimeImmutable('-1 day'));
        $doneButLate->status = 'done';
        $this->assertFalse($doneButLate->isOverdue());
    }
}
