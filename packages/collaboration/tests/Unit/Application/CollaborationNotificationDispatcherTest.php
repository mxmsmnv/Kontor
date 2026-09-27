<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Unit\Application;

use Kontor\Collaboration\Application\CollaborationNotificationDispatcher;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use PHPUnit\Framework\TestCase;

final class CollaborationNotificationDispatcherTest extends TestCase
{
    public function test_it_queues_one_idempotent_notification_per_valid_non_author_recipient(): void
    {
        $queue = new class implements QueueInterface {
            /** @var array<int, array{job: JobInterface, options: QueueOptions|null}> */
            public array $dispatched = [];

            public function dispatch(JobInterface $job, ?QueueOptions $options = null): string
            {
                $this->dispatched[] = ['job' => $job, 'options' => $options];

                return 'job-' . count($this->dispatched);
            }

            public function later(
                \DateTimeImmutable $when,
                JobInterface $job,
                ?QueueOptions $options = null,
            ): string {
                return $this->dispatch($job, $options);
            }

            public function cancel(string $jobId): bool
            {
                return false;
            }
        };

        $jobs = (new CollaborationNotificationDispatcher($queue))->dispatch(
            organizationUid: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            commentUid: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            entityType: 'task',
            entityUid: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            authorUserId: 41,
            authorLabel: 'Roisin',
            fromAddress: 'notifications@kontor.local',
            subject: 'New comment',
            body: 'Please review.',
            recipients: [
                ['userId' => 41, 'email' => 'author@example.test', 'reason' => 'follow'],
                ['userId' => 42, 'email' => 'WATCHER@EXAMPLE.TEST', 'reason' => 'mention'],
                ['userId' => 43, 'email' => 'not-an-email', 'reason' => 'follow'],
            ],
        );

        $this->assertSame(['job-1'], $jobs);
        $this->assertCount(1, $queue->dispatched);
        $this->assertSame('collaboration.notify', $queue->dispatched[0]['job']->jobType());
        $this->assertSame('watcher@example.test', $queue->dispatched[0]['job']->payload()['recipientEmail']);
        $this->assertSame('mention', $queue->dispatched[0]['job']->payload()['reason']);
        $this->assertSame('notifications', $queue->dispatched[0]['options']?->queue);
        $this->assertSame(
            'collaboration:01ARZ3NDEKTSV4RRFFQ69G5FAW:42',
            $queue->dispatched[0]['options']?->idempotencyKey,
        );
    }
}
