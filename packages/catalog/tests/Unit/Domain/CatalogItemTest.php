<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Unit\Domain;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class CatalogItemTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $item = CatalogItem::create('org_01', ['en' => 'Widget']);

        $this->assertSame('product', $item->itemType);
        $this->assertSame('pcs', $item->unitCode);
        $this->assertSame('active', $item->status);
        $this->assertFalse($item->trackInventory);
        $this->assertNull($item->salesPrice);
    }

    public function test_is_service(): void
    {
        $product = CatalogItem::create('org_01', ['en' => 'Widget'], itemType: 'product');
        $service = CatalogItem::create('org_01', ['en' => 'Consulting'], itemType: 'service');

        $this->assertFalse($product->isService());
        $this->assertTrue($service->isService());
    }

    public function test_title_in_falls_back_to_english(): void
    {
        $item = CatalogItem::create('org_01', ['en' => 'Widget', 'fr' => 'Gadget']);

        $this->assertSame('Gadget', $item->titleIn('fr'));
        $this->assertSame('Widget', $item->titleIn('de'));
    }

    public function test_carries_money_value_objects(): void
    {
        $item = CatalogItem::create('org_01', ['en' => 'Widget'], salesPrice: Money::ofMinor(1999, 'EUR'));

        $this->assertSame(1999, $item->salesPrice->amountMinor());
        $this->assertSame('EUR', $item->salesPrice->currencyCode());
    }

    public function test_each_item_gets_a_unique_uid(): void
    {
        $a = CatalogItem::create('org_01', ['en' => 'Widget']);
        $b = CatalogItem::create('org_01', ['en' => 'Widget']);

        $this->assertFalse($a->uid->equals($b->uid));
    }

    public function test_duplicate_copies_commercial_data_but_clears_unique_identifiers(): void
    {
        $original = CatalogItem::create(
            'org_01',
            ['en' => 'Widget', 'fr' => 'Gadget'],
            itemType: 'product',
            sku: 'WID-001',
            barcode: '900000000001',
            description: ['en' => 'Original description'],
            categoryUid: '01J00000000000000000000000',
            unitCode: 'box',
            taxCode: 'standard',
            salesPrice: Money::ofMinor(1999, 'EUR'),
            purchasePrice: Money::ofMinor(1200, 'EUR'),
            costPrice: Money::ofMinor(900, 'EUR'),
            trackInventory: true,
            metadata: ['supplier' => 'Acme'],
        );

        $duplicate = $original->duplicate();

        $this->assertFalse($original->uid->equals($duplicate->uid));
        $this->assertSame(['en' => 'Widget (copy)', 'fr' => 'Gadget (copy)'], $duplicate->title);
        $this->assertSame($original->description, $duplicate->description);
        $this->assertNull($duplicate->sku);
        $this->assertNull($duplicate->barcode);
        $this->assertSame('inactive', $duplicate->status);
        $this->assertSame($original->categoryUid, $duplicate->categoryUid);
        $this->assertSame('box', $duplicate->unitCode);
        $this->assertSame('standard', $duplicate->taxCode);
        $this->assertTrue($duplicate->trackInventory);
        $this->assertTrue($original->salesPrice->equals($duplicate->salesPrice));
        $this->assertTrue($original->purchasePrice->equals($duplicate->purchasePrice));
        $this->assertTrue($original->costPrice->equals($duplicate->costPrice));
        $this->assertSame(['supplier' => 'Acme'], $duplicate->metadata);
    }
}
