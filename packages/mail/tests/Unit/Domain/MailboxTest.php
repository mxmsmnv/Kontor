<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Domain;

use Kontor\Mail\Domain\Mailbox;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    public function test_open_defaults_to_active(): void
    {
        $mailbox = Mailbox::open('org_1', 'Support', 'support@example.com');

        $this->assertTrue($mailbox->isActive());
        $this->assertSame('Support', $mailbox->name);
        $this->assertSame('support@example.com', $mailbox->emailAddress);
    }
}
