<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use Kontor\Tasks\Domain\TaskReminder;
use Kontor\Tasks\Infrastructure\Queue\TaskReminderDeliveryJob;

final class TaskReminderDispatcher
{
    public function __construct(
        private readonly TaskReminderService $reminders,
        private readonly QueueInterface $queue,
    ) {
    }

    /**
     * @return array{reminder: TaskReminder, jobUid: string}
     */
    public function scheduleEmail(
        string $organizationUid,
        string $taskUid,
        string $taskTitle,
        \DateTimeImmutable $remindAt,
        int $recipientUserId,
        string $recipientEmail,
        string $fromAddress,
        ?int $createdBy = null,
    ): array {
        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Task reminder recipient email is invalid.');
        }
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Task reminder sender email is invalid.');
        }

        $reminder = $this->reminders->schedule($organizationUid, $taskUid, $remindAt, 'email');
        $job = TaskReminderDeliveryJob::forReminder(
            organizationUid: $organizationUid,
            reminderUid: $reminder->uid->toString(),
            taskUid: $taskUid,
            taskTitle: $taskTitle,
            recipientUserId: $recipientUserId,
            recipientEmail: strtolower($recipientEmail),
            fromAddress: strtolower($fromAddress),
            createdBy: $createdBy,
        );
        $jobUid = $this->queue->later($remindAt, $job, new QueueOptions(
            queue: 'notifications',
            priority: 40,
            maxAttempts: 5,
            idempotencyKey: 'task-reminder:' . $reminder->uid->toString(),
        ));

        return ['reminder' => $reminder, 'jobUid' => $jobUid];
    }
}
