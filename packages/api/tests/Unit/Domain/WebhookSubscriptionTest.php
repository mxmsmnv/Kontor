<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Domain;

use Kontor\API\Domain\WebhookSubscription;
use PHPUnit\Framework\TestCase;

final class WebhookSubscriptionTest extends TestCase
{
    public function test_record_failure_increments_and_record_success_resets(): void
    {
        $subscription = WebhookSubscription::create('org_1', 'https://example.com/hook', 'invoice.issued', 'secret');

        $subscription->recordFailure();
        $subscription->recordFailure();
        $this->assertSame(2, $subscription->consecutiveFailures);

        $subscription->recordSuccess();
        $this->assertSame(0, $subscription->consecutiveFailures);
    }

    public function test_disable_sets_status(): void
    {
        $subscription = WebhookSubscription::create('org_1', 'https://example.com/hook', 'invoice.issued', 'secret');
        $this->assertTrue($subscription->isActive());

        $subscription->disable();

        $this->assertFalse($subscription->isActive());
        $this->assertSame('disabled', $subscription->status);
    }
}
