<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Infrastructure\Queue\CollaborationNotificationJob;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\Mail\Contracts\MailSenderInterface;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\SDK\Contracts\JobProgressReporterInterface;

final class CollaborationNotificationJobTest extends DatabaseTestCase
{
    public function test_job_delivers_through_mail_and_persists_outbound_history(): void
    {
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
        $messages = new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
        $mail = new OutboundMailService($sender, $messages);
        $progress = new class implements JobProgressReporterInterface {
            public int $percent = 0;

            public function report(int $percent): void
            {
                $this->percent = $percent;
            }
        };
        $queued = CollaborationNotificationJob::forRecipient(
            organizationUid: $this->organizationUid,
            commentUid: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            entityType: 'task',
            entityUid: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            authorUserId: 41,
            authorLabel: 'Roisin',
            recipientUserId: 42,
            recipientEmail: 'watcher@example.test',
            reason: 'mention',
            fromAddress: 'notifications@kontor.local',
            subject: 'New comment',
            body: 'Please review.',
        );
        $links = new EntityLinkingService(
            new RelationRepository($this->pdo, new OrganizationRepository($this->pdo)),
        );
        $job = new CollaborationNotificationJob($queued->payload(), $mail, $links);

        $job->handle($job->payload(), $progress);

        $this->assertSame(100, $progress->percent);
        $this->assertCount(1, $sender->sent);
        $this->assertSame(['watcher@example.test'], $sender->sent[0]['toAddresses']);
        $history = $messages->forOrganization($this->organizationUid);
        $this->assertCount(1, $history);
        $this->assertSame('sent', $history[0]->status);
        $this->assertSame('New comment', $history[0]->subject);
        $this->assertSame(41, $history[0]->createdBy);
        $linked = $links->linkedEntities($this->organizationUid, $history[0]->uid->toString());
        $this->assertCount(1, $linked);
        $this->assertSame('task', $linked[0]['targetType']);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAX', $linked[0]['targetUid']);
    }

    public function test_failed_mail_transport_throws_so_queue_can_retry(): void
    {
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
        $messages = new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
        $mail = new OutboundMailService($sender, $messages);
        $progress = new class implements JobProgressReporterInterface {
            public function report(int $percent): void
            {
            }
        };
        $queued = CollaborationNotificationJob::forRecipient(
            organizationUid: $this->organizationUid,
            commentUid: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            entityType: 'task',
            entityUid: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            authorUserId: 41,
            authorLabel: 'Roisin',
            recipientUserId: 42,
            recipientEmail: 'watcher@example.test',
            reason: 'mention',
            fromAddress: 'notifications@kontor.local',
            subject: 'New comment',
            body: 'Please review.',
        );
        $job = new CollaborationNotificationJob($queued->payload(), $mail);

        try {
            $job->handle($job->payload(), $progress);
            $this->fail('A failed mail transport must fail the Queue job.');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP unavailable', $e->getMessage());
        }

        $history = $messages->forOrganization($this->organizationUid);
        $this->assertCount(1, $history);
        $this->assertSame('failed', $history[0]->status);
    }
}
