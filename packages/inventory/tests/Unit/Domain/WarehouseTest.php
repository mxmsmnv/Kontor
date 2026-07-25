<?php

declare(strict_types=1);

namespace Kontor\Inventory\Tests\Unit\Domain;

use Kontor\Inventory\Domain\Warehouse;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    public function test_create_starts_active(): void
    {
        $warehouse = Warehouse::create('org_01', 'MAIN', 'Main Warehouse');

        $this->assertTrue($warehouse->isActive());
        $this->assertSame('active', $warehouse->status);
    }

    public function test_is_active_reflects_status(): void
    {
        $warehouse = Warehouse::create('org_01', 'MAIN', 'Main Warehouse');
        $warehouse->status = 'inactive';

        $this->assertFalse($warehouse->isActive());
    }
}
