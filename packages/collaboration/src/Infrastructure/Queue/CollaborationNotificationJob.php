<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Infrastructure\Queue;

use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use RuntimeException;

final class CollaborationNotificationJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly ?OutboundMailService $mail = null,
        private readonly ?EntityLinkingService $links = null,
    ) {
    }

    public static function forRecipient(
        string $organizationUid,
        string $commentUid,
        string $entityType,
        string $entityUid,
        ?int $authorUserId,
        string $authorLabel,
        int $recipientUserId,
        string $recipientEmail,
        string $reason,
        string $fromAddress,
        string $subject,
        string $body,
    ): self {
        return new self([
            'organizationUid' => $organizationUid,
            'commentUid' => $commentUid,
            'entityType' => $entityType,
            'entityUid' => $entityUid,
            'authorUserId' => $authorUserId,
            'authorLabel' => $authorLabel,
            'recipientUserId' => $recipientUserId,
            'recipientEmail' => $recipientEmail,
            'reason' => $reason,
            'fromAddress' => $fromAddress,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    public function jobType(): string
    {
        return 'collaboration.notify';
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        if ($this->mail === null) {
            throw new RuntimeException(
                'CollaborationNotificationJob requires OutboundMailService; register its queue factory with Mail injected.'
            );
        }

        $message = $this->mail->send(
            organizationId: (string) $payload['organizationUid'],
            mailboxUid: null,
            fromAddress: (string) $payload['fromAddress'],
            toAddresses: [(string) $payload['recipientEmail']],
            ccAddresses: [],
            subject: (string) $payload['subject'],
            bodyText: (string) $payload['body'],
            createdBy: isset($payload['authorUserId']) ? (int) $payload['authorUserId'] : null,
        );
        if ($message->status !== 'sent') {
            throw new RuntimeException($message->error ?? 'Collaboration notification delivery failed.');
        }
        $this->links?->link(
            organizationUid: (string) $payload['organizationUid'],
            messageUid: $message->uid->toString(),
            entityType: (string) $payload['entityType'],
            entityUid: (string) $payload['entityUid'],
            createdBy: isset($payload['authorUserId']) ? (int) $payload['authorUserId'] : null,
        );

        $progress->report(100);
    }
}
