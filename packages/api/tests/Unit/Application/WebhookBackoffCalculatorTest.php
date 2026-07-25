<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\WebhookBackoffCalculator;
use PHPUnit\Framework\TestCase;

final class WebhookBackoffCalculatorTest extends TestCase
{
    public function test_backoff_doubles_each_attempt(): void
    {
        $calculator = new WebhookBackoffCalculator(maxAttempts: 8, baseBackoffSeconds: 30);
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $this->assertSame('2026-01-01T00:00:30+00:00', $calculator->nextAttemptAt(0, $now)?->format(DATE_ATOM));
        $this->assertSame('2026-01-01T00:01:00+00:00', $calculator->nextAttemptAt(1, $now)?->format(DATE_ATOM));
        $this->assertSame('2026-01-01T00:02:00+00:00', $calculator->nextAttemptAt(2, $now)?->format(DATE_ATOM));
    }

    public function test_returns_null_once_max_attempts_is_reached(): void
    {
        $calculator = new WebhookBackoffCalculator(maxAttempts: 3);

        $this->assertNotNull($calculator->nextAttemptAt(0));
        $this->assertNotNull($calculator->nextAttemptAt(1));
        $this->assertNull($calculator->nextAttemptAt(2));
    }

    public function test_should_disable_threshold(): void
    {
        $calculator = new WebhookBackoffCalculator(disableAfterConsecutiveFailures: 10);

        $this->assertFalse($calculator->shouldDisable(9));
        $this->assertTrue($calculator->shouldDisable(10));
        $this->assertTrue($calculator->shouldDisable(11));
    }
}
