<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Infrastructure\Import\ItemImportProvider;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ImportContext;

final class ItemImportProviderTest extends DatabaseTestCase
{
    private function provider(): ItemImportProvider
    {
        return new ItemImportProvider(new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->organizationUid, 'batch_01', false, 'user', 'usr_01');
    }

    public function test_validate_requires_at_least_one_title(): void
    {
        $result = $this->provider()->validate(['sku' => 'WID-001'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['catalog_item.title.required'], $result->errors['title']);
    }

    public function test_validate_rejects_an_unknown_unit_code(): void
    {
        $result = $this->provider()->validate(['title_en' => 'Widget', 'unit_code' => 'parsec'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['catalog_item.unit_code.unknown'], $result->errors['unit_code']);
    }

    public function test_validate_rejects_an_unknown_tax_code(): void
    {
        $result = $this->provider()->validate(['title_en' => 'Widget', 'tax_code' => 'luxury'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['catalog_item.tax_code.unknown'], $result->errors['tax_code']);
    }

    public function test_validate_requires_currency_alongside_a_price(): void
    {
        $result = $this->provider()->validate(['title_en' => 'Widget', 'sales_price_minor' => 1999], $this->context());

        $this->assertFalse($result->valid);
        $this->assertArrayHasKey('sales_price_minor', $result->errors);
    }

    public function test_validate_accepts_a_complete_record(): void
    {
        $result = $this->provider()->validate(
            ['title_en' => 'Widget', 'unit_code' => 'kg', 'tax_code' => 'standard', 'sales_price_minor' => 1999, 'sales_currency' => 'EUR'],
            $this->context(),
        );

        $this->assertTrue($result->valid);
    }

    public function test_import_creates_a_new_item_with_localized_title(): void
    {
        $provider = $this->provider();
        $result = $provider->import(['title_en' => 'Widget', 'title_fr' => 'Gadget', 'sku' => 'WID-001'], $this->context());

        $this->assertSame('created', $result->outcome);

        $item = (new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo)))->find($result->entityUid);
        $this->assertSame(['en' => 'Widget', 'fr' => 'Gadget'], $item->title);
    }

    public function test_import_updates_a_duplicate_found_by_sku(): void
    {
        $provider = $this->provider();
        $first = $provider->import(['title_en' => 'Widget', 'sku' => 'WID-001'], $this->context());

        $result = $provider->import(['title_en' => 'Widget', 'sku' => 'WID-001', 'status' => 'discontinued'], $this->context());

        $this->assertSame('updated', $result->outcome);
        $this->assertSame($first->entityUid, $result->entityUid);
    }

    public function test_import_parses_prices_and_booleans(): void
    {
        $provider = $this->provider();
        $result = $provider->import(
            ['title_en' => 'Widget', 'sales_price_minor' => '1999', 'sales_currency' => 'EUR', 'track_inventory' => 'yes'],
            $this->context(),
        );

        $item = (new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo)))->find($result->entityUid);
        $this->assertSame(1999, $item->salesPrice->amountMinor());
        $this->assertTrue($item->trackInventory);
    }
}
