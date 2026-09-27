<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Domain;

use Kontor\AI\Domain\PendingAIAction;
use PHPUnit\Framework\TestCase;

final class PendingAIActionTest extends TestCase
{
    public function test_create_starts_pending(): void
    {
        $action = PendingAIAction::create('org_1', 'draft', ['text' => 'in'], ['draft' => 'out']);

        $this->assertTrue($action->isPending());
        $this->assertNull($action->decidedAt);
    }

    public function test_approve(): void
    {
        $action = PendingAIAction::create('org_1', 'draft', [], []);

        $action->approve(42);

        $this->assertSame('approved', $action->status);
        $this->assertSame(42, $action->decidedBy);
        $this->assertNotNull($action->decidedAt);
        $this->assertFalse($action->isPending());
    }

    public function test_reject(): void
    {
        $action = PendingAIAction::create('org_1', 'draft', [], []);

        $action->reject(7);

        $this->assertSame('rejected', $action->status);
        $this->assertSame(7, $action->decidedBy);
    }
}
