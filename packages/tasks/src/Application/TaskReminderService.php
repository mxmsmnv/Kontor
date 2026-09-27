<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\Tasks\Domain\TaskReminder;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;

/**
 * The "reminders" milestone: scheduling and tracking reminder records.
 */
final class TaskReminderService
{
    public function __construct(private readonly TaskReminderRepository $reminders)
    {
    }

    public function schedule(string $organizationUid, string $taskUid, \DateTimeImmutable $remindAt, string $channel = 'app'): TaskReminder
    {
        $reminder = TaskReminder::create($organizationUid, $taskUid, $remindAt, $channel);
        $this->reminders->save($reminder);

        return $reminder;
    }

    /**
     * @return TaskReminder[]
     */
    public function dueReminders(string $organizationUid, ?\DateTimeImmutable $asOf = null): array
    {
        return $this->reminders->due($organizationUid, $asOf);
    }

    public function reminder(string $reminderUid): TaskReminder
    {
        return $this->reminders->require($reminderUid);
    }

    public function markSent(string $reminderUid): TaskReminder
    {
        $reminder = $this->reminders->require($reminderUid);
        $reminder->sentAt = new \DateTimeImmutable();
        $this->reminders->save($reminder);

        return $reminder;
    }
}
