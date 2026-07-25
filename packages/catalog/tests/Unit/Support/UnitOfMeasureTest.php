<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Unit\Support;

use Kontor\Catalog\Support\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class UnitOfMeasureTest extends TestCase
{
    public function test_known_defaults(): void
    {
        $units = new UnitOfMeasure();

        $this->assertTrue($units->isKnown('pcs'));
        $this->assertTrue($units->isKnown('kg'));
        $this->assertSame('Kilogram', $units->label('kg'));
    }

    public function test_unknown_code(): void
    {
        $units = new UnitOfMeasure();

        $this->assertFalse($units->isKnown('parsec'));
        $this->assertNull($units->label('parsec'));
    }

    public function test_additional_units_extend_without_mutating_other_instances(): void
    {
        $extended = new UnitOfMeasure(['crate' => 'Crate']);
        $default = new UnitOfMeasure();

        $this->assertTrue($extended->isKnown('crate'));
        $this->assertFalse($default->isKnown('crate'));
    }

    public function test_additional_units_can_override_a_default_label(): void
    {
        $units = new UnitOfMeasure(['kg' => 'Kilo']);

        $this->assertSame('Kilo', $units->label('kg'));
    }
}
