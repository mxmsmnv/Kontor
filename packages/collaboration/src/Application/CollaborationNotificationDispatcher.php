<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Application;

use Kontor\Collaboration\Infrastructure\Queue\CollaborationNotificationJob;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;

final class CollaborationNotificationDispatcher
{
    public function __construct(private readonly QueueInterface $queue)
    {
    }

    /**
     * @param array<int, array{userId: int, email: string, reason: string}> $recipients
     *
     * @return string[] dispatched job UIDs
     */
    public function dispatch(
        string $organizationUid,
        string $commentUid,
        string $entityType,
        string $entityUid,
        ?int $authorUserId,
        string $authorLabel,
        string $fromAddress,
        string $subject,
        string $body,
        array $recipients,
    ): array {
        $jobs = [];

        foreach ($recipients as $recipient) {
            if (
                $recipient['userId'] === $authorUserId
                || filter_var($recipient['email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                continue;
            }

            $job = CollaborationNotificationJob::forRecipient(
                organizationUid: $organizationUid,
                commentUid: $commentUid,
                entityType: $entityType,
                entityUid: $entityUid,
                authorUserId: $authorUserId,
                authorLabel: $authorLabel,
                recipientUserId: $recipient['userId'],
                recipientEmail: strtolower($recipient['email']),
                reason: $recipient['reason'],
                fromAddress: $fromAddress,
                subject: $subject,
                body: $body,
            );

            $jobs[] = $this->queue->dispatch($job, new QueueOptions(
                queue: 'notifications',
                priority: 50,
                maxAttempts: 5,
                idempotencyKey: "collaboration:{$commentUid}:{$recipient['userId']}",
            ));
        }

        return $jobs;
    }
}
