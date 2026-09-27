<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Unit\Domain;

use Kontor\CRM\Domain\Stage;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    public function test_display_name_in_falls_back_to_english(): void
    {
        $stage = Stage::create('pl_01', 'qualified', ['en' => 'Qualified', 'de' => 'Qualifiziert']);

        $this->assertSame('Qualifiziert', $stage->displayNameIn('de'));
        $this->assertSame('Qualified', $stage->displayNameIn('fr'));
    }

    public function test_is_won_and_is_lost(): void
    {
        $open = Stage::create('pl_01', 'qualified', ['en' => 'Qualified'], stateType: 'open');
        $won = Stage::create('pl_01', 'won', ['en' => 'Won'], stateType: 'won');
        $lost = Stage::create('pl_01', 'lost', ['en' => 'Lost'], stateType: 'lost');

        $this->assertFalse($open->isWon());
        $this->assertFalse($open->isLost());
        $this->assertTrue($won->isWon());
        $this->assertTrue($lost->isLost());
    }
}
