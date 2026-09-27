<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\Mail\Contracts\MailSenderInterface;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use Kontor\Tasks\Application\TaskReminderDispatcher;
use Kontor\Tasks\Application\TaskReminderService;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use Kontor\Tasks\Infrastructure\Queue\TaskReminderDeliveryJob;

final class TaskReminderDeliveryTest extends DatabaseTestCase
{
    public function test_delayed_job_sends_links_and_marks_the_reminder_once(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $tasks = new TaskRepository($this->pdo, $organizations);
        $task = Task::create($this->organizationUid, 'Review proposal', assignedTo: 42);
        $tasks->save($task);
        $reminders = new TaskReminderService(new TaskReminderRepository($this->pdo, $organizations));
        $queue = new class implements QueueInterface {
            public ?JobInterface $job = null;
            public ?QueueOptions $options = null;
            public ?\DateTimeImmutable $when = null;

            public function dispatch(JobInterface $job, ?QueueOptions $options = null): string
            {
                $this->job = $job;
                $this->options = $options;

                return 'job-1';
            }

            public function later(
                \DateTimeImmutable $when,
                JobInterface $job,
                ?QueueOptions $options = null,
            ): string {
                $this->when = $when;

                return $this->dispatch($job, $options);
            }

            public function cancel(string $jobId): bool
            {
                return false;
            }
        };
        $remindAt = new \DateTimeImmutable('+10 minutes');
        $scheduled = (new TaskReminderDispatcher($reminders, $queue))->scheduleEmail(
            organizationUid: $this->organizationUid,
            taskUid: $task->uid->toString(),
            taskTitle: $task->title,
            remindAt: $remindAt,
            recipientUserId: 42,
            recipientEmail: 'ASSIGNEE@EXAMPLE.TEST',
            fromAddress: 'NOTIFICATIONS@KONTOR.LOCAL',
            createdBy: 41,
        );

        $this->assertSame('job-1', $scheduled['jobUid']);
        $this->assertSame($remindAt, $queue->when);
        $this->assertSame('notifications', $queue->options?->queue);
        $this->assertSame(
            'task-reminder:' . $scheduled['reminder']->uid->toString(),
            $queue->options?->idempotencyKey,
        );
        $this->assertSame('assignee@example.test', $queue->job?->payload()['recipientEmail']);

        $sender = new class implements MailSenderInterface {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            public function send(
                string $fromAddress,
                array $toAddresses,
                array $ccAddresses,
                string $subject,
                string $bodyText,
            ): void {
                $this->sent[] = compact('fromAddress', 'toAddresses', 'ccAddresses', 'subject', 'bodyText');
            }
        };
        $messages = new MailMessageRepository($this->pdo, $organizations);
        $links = new EntityLinkingService(new RelationRepository($this->pdo, $organizations));
        $job = new TaskReminderDeliveryJob(
            $queue->job?->payload() ?? [],
            $reminders,
            new OutboundMailService($sender, $messages),
            $links,
        );
        $progress = new class implements JobProgressReporterInterface {
            public int $percent = 0;

            public function report(int $percent): void
            {
                $this->percent = $percent;
            }
        };

        $job->handle($job->payload(), $progress);
        $job->handle($job->payload(), $progress);

        $this->assertSame(100, $progress->percent);
        $this->assertCount(1, $sender->sent);
        $this->assertTrue($reminders->reminder($scheduled['reminder']->uid->toString())->isSent());
        $history = $messages->forOrganization($this->organizationUid);
        $this->assertCount(1, $history);
        $this->assertSame('Task reminder: Review proposal', $history[0]->subject);
        $linked = $links->linkedEntities($this->organizationUid, $history[0]->uid->toString());
        $this->assertCount(1, $linked);
        $this->assertSame($task->uid->toString(), $linked[0]['targetUid']);
    }

    public function test_failed_transport_leaves_reminder_unsent_for_queue_retry(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $reminders = new TaskReminderService(new TaskReminderRepository($this->pdo, $organizations));
        $reminder = $reminders->schedule(
            $this->organizationUid,
            '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            new \DateTimeImmutable(),
            'email',
        );
        $queued = TaskReminderDeliveryJob::forReminder(
            organizationUid: $this->organizationUid,
            reminderUid: $reminder->uid->toString(),
            taskUid: $reminder->taskUid,
            taskTitle: 'Review proposal',
            recipientUserId: 42,
            recipientEmail: 'assignee@example.test',
            fromAddress: 'notifications@kontor.local',
            createdBy: 41,
        );
        $messages = new MailMessageRepository($this->pdo, $organizations);
        $sender = new class implements MailSenderInterface {
            public function send(
                string $fromAddress,
                array $toAddresses,
                array $ccAddresses,
                string $subject,
                string $bodyText,
            ): void {
                throw new \RuntimeException('SMTP unavailable');
            }
        };
        $job = new TaskReminderDeliveryJob(
            $queued->payload(),
            $reminders,
            new OutboundMailService($sender, $messages),
        );
        $progress = new class implements JobProgressReporterInterface {
            public function report(int $percent): void
            {
            }
        };

        try {
            $job->handle($job->payload(), $progress);
            $this->fail('A failed transport must leave the Queue job retryable.');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP unavailable', $e->getMessage());
        }

        $this->assertFalse($reminders->reminder($reminder->uid->toString())->isSent());
        $history = $messages->forOrganization($this->organizationUid);
        $this->assertCount(1, $history);
        $this->assertSame('failed', $history[0]->status);
    }
}
