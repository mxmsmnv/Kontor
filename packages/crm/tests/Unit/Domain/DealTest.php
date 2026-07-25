<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Unit\Domain;

use Kontor\CRM\Domain\Deal;
use PHPUnit\Framework\TestCase;

final class DealTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $deal = Deal::create('org_01', 'pl_01', 'stage_01', 'Big deal');

        $this->assertSame('open', $deal->status);
        $this->assertTrue($deal->isOpen());
        $this->assertNull($deal->wonAt);
        $this->assertNull($deal->lostAt);
    }

    public function test_each_deal_gets_a_unique_uid(): void
    {
        $a = Deal::create('org_01', 'pl_01', 'stage_01', 'Big deal');
        $b = Deal::create('org_01', 'pl_01', 'stage_01', 'Big deal');

        $this->assertFalse($a->uid->equals($b->uid));
    }
}
