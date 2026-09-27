<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Domain;

use Kontor\API\Domain\WebhookDelivery;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryTest extends TestCase
{
    public function test_mark_delivered(): void
    {
        $delivery = WebhookDelivery::create('org_1', 'sub_1', 'evt_1', 'invoice.issued', ['foo' => 'bar']);

        $delivery->markDelivered(200);

        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(200, $delivery->responseCode);
        $this->assertNull($delivery->lastError);
        $this->assertNull($delivery->nextAttemptAt);
        $this->assertNotNull($delivery->deliveredAt);
    }

    public function test_mark_attempt_failed_with_a_retry_scheduled(): void
    {
        $delivery = WebhookDelivery::create('org_1', 'sub_1', 'evt_1', 'invoice.issued', []);
        $nextAttempt = new \DateTimeImmutable('+30 seconds');

        $delivery->markAttemptFailed(500, 'HTTP 500', $nextAttempt);

        $this->assertSame(1, $delivery->attemptCount);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame($nextAttempt, $delivery->nextAttemptAt);
        $this->assertSame('HTTP 500', $delivery->lastError);
    }

    public function test_mark_attempt_failed_with_retries_exhausted(): void
    {
        $delivery = WebhookDelivery::create('org_1', 'sub_1', 'evt_1', 'invoice.issued', []);

        $delivery->markAttemptFailed(500, 'HTTP 500', null);

        $this->assertSame('exhausted', $delivery->status);
        $this->assertNull($delivery->nextAttemptAt);
    }

    public function test_reset_for_replay(): void
    {
        $delivery = WebhookDelivery::create('org_1', 'sub_1', 'evt_1', 'invoice.issued', []);
        $delivery->markAttemptFailed(500, 'HTTP 500', null);
        $this->assertSame('exhausted', $delivery->status);

        $delivery->resetForReplay();

        $this->assertSame('pending', $delivery->status);
        $this->assertNotNull($delivery->nextAttemptAt);
    }
}
