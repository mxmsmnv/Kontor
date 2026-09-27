<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Mail\Domain\Mailbox;
use Kontor\Mail\Infrastructure\Persistence\MailboxRepository;

/**
 * The "shared mailboxes" milestone.
 */
final class MailboxService
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
    ) {
    }

    public function open(string $organizationId, string $name, string $emailAddress, ?int $createdBy = null): Mailbox
    {
        $mailbox = Mailbox::open($organizationId, $name, $emailAddress, $createdBy);
        $this->mailboxes->save($mailbox);

        return $mailbox;
    }

    public function archive(string $mailboxUid): void
    {
        $this->mailboxes->archive($mailboxUid);
    }

    public function restore(string $mailboxUid): void
    {
        $this->mailboxes->restore($mailboxUid);
    }
}
