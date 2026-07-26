<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Application;

use Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter;
use PHPUnit\Framework\TestCase;

final class RawEmailForwardAdapterTest extends TestCase
{
    public function test_key(): void
    {
        $this->assertSame('forward', (new RawEmailForwardAdapter())->key());
    }

    public function test_fetch_drains_the_queue(): void
    {
        $adapter = new RawEmailForwardAdapter();
        $adapter->pushRaw("From: alice@example.com\nSubject: Hi\n\nBody");

        $this->assertCount(1, $adapter->fetch());
        $this->assertCount(0, $adapter->fetch());
    }

    public function test_a_malformed_raw_email_is_silently_dropped_not_thrown(): void
    {
        $adapter = new RawEmailForwardAdapter();
        $adapter->pushRaw('this has no From header at all');
        $adapter->pushRaw("From: alice@example.com\nSubject: Valid\n\nBody");

        $messages = $adapter->fetch();

        $this->assertCount(1, $messages);
        $this->assertSame('Valid', $messages[0]->subject);
    }
}
