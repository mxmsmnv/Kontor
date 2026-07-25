<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Unit\Domain;

use Kontor\Catalog\Domain\PriceList;
use PHPUnit\Framework\TestCase;

final class PriceListTest extends TestCase
{
    public function test_is_active_on_respects_status_and_window(): void
    {
        $active = PriceList::create('org_01', 'Retail', 'eur');
        $this->assertTrue($active->isActiveOn(new \DateTimeImmutable()));

        $inactive = PriceList::create('org_01', 'Retail', 'EUR', status: 'inactive');
        $this->assertFalse($inactive->isActiveOn(new \DateTimeImmutable()));

        $seasonal = PriceList::create(
            'org_01', 'Summer', 'EUR',
            validFrom: new \DateTimeImmutable('2026-06-01'),
            validTo: new \DateTimeImmutable('2026-08-31'),
        );
        $this->assertFalse($seasonal->isActiveOn(new \DateTimeImmutable('2026-01-01')));
        $this->assertTrue($seasonal->isActiveOn(new \DateTimeImmutable('2026-07-01')));
    }

    public function test_currency_code_is_uppercased(): void
    {
        $priceList = PriceList::create('org_01', 'Retail', 'eur');

        $this->assertSame('EUR', $priceList->currencyCode);
    }
}
