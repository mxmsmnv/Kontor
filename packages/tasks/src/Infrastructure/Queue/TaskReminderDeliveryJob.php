<?php

declare(strict_types=1);

namespace Kontor\Tasks\Infrastructure\Queue;

use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use Kontor\Tasks\Application\TaskReminderService;
use RuntimeException;

final class TaskReminderDeliveryJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly ?TaskReminderService $reminders = null,
        private readonly ?OutboundMailService $mail = null,
        private readonly ?EntityLinkingService $links = null,
    ) {
    }

    public static function forReminder(
        string $organizationUid,
        string $reminderUid,
        string $taskUid,
        string $taskTitle,
        int $recipientUserId,
        string $recipientEmail,
        string $fromAddress,
        ?int $createdBy = null,
    ): self {
        return new self([
            'organizationUid' => $organizationUid,
            'reminderUid' => $reminderUid,
            'taskUid' => $taskUid,
            'taskTitle' => $taskTitle,
            'recipientUserId' => $recipientUserId,
            'recipientEmail' => $recipientEmail,
            'fromAddress' => $fromAddress,
            'createdBy' => $createdBy,
        ]);
    }

    public function jobType(): string
    {
        return 'tasks.reminder';
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        if ($this->reminders === null || $this->mail === null) {
            throw new RuntimeException(
                'TaskReminderDeliveryJob requires TaskReminderService and OutboundMailService; register its queue factory.'
            );
        }

        $reminder = $this->reminders->reminder((string) $payload['reminderUid']);
        if ($reminder->isSent()) {
            $progress->report(100);

            return;
        }

        $message = $this->mail->send(
            organizationId: (string) $payload['organizationUid'],
            mailboxUid: null,
            fromAddress: (string) $payload['fromAddress'],
            toAddresses: [(string) $payload['recipientEmail']],
            ccAddresses: [],
            subject: 'Task reminder: ' . (string) $payload['taskTitle'],
            bodyText: 'Reminder for task “' . (string) $payload['taskTitle'] . '”.',
            createdBy: isset($payload['createdBy']) ? (int) $payload['createdBy'] : null,
        );
        if ($message->status !== 'sent') {
            throw new RuntimeException($message->error ?? 'Task reminder delivery failed.');
        }

        $this->links?->link(
            organizationUid: (string) $payload['organizationUid'],
            messageUid: $message->uid->toString(),
            entityType: 'task',
            entityUid: (string) $payload['taskUid'],
            createdBy: isset($payload['createdBy']) ? (int) $payload['createdBy'] : null,
        );
        $this->reminders->markSent((string) $payload['reminderUid']);
        $progress->report(100);
    }
}
