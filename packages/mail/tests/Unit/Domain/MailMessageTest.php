<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Domain;

use Kontor\Mail\Domain\MailMessage;
use PHPUnit\Framework\TestCase;

final class MailMessageTest extends TestCase
{
    public function test_outbound_starts_queued(): void
    {
        $message = MailMessage::outbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');

        $this->assertSame('queued', $message->status);
        $this->assertTrue($message->isOutbound());
    }

    public function test_inbound_starts_received(): void
    {
        $message = MailMessage::inbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body', new \DateTimeImmutable());

        $this->assertSame('received', $message->status);
        $this->assertFalse($message->isOutbound());
    }

    public function test_mark_sent(): void
    {
        $message = MailMessage::outbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');

        $message->markSent();

        $this->assertSame('sent', $message->status);
        $this->assertNull($message->error);
    }

    public function test_mark_failed(): void
    {
        $message = MailMessage::outbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');

        $message->markFailed('SMTP connection refused');

        $this->assertSame('failed', $message->status);
        $this->assertSame('SMTP connection refused', $message->error);
    }

    public function test_assign_to(): void
    {
        $message = MailMessage::inbound('org_1', null, 'a@example.com', [], [], 'Hi', 'Body', new \DateTimeImmutable());
        $this->assertNull($message->assignedTo);

        $message->assignTo(42);

        $this->assertSame(42, $message->assignedTo);
    }
}
