# Changelog

All notable changes to `kontor/catalog` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Organization-scoped catalog listing, search, type filters, archived views,
  exact counts, and offset pagination for the admin workspace.
- Initial alpha (Substage 3.2): `kontor_catalog_items` (products/services
  via `item_type`), `kontor_catalog_categories` (schema gap-fill, like
  Contacts' tags), `kontor_catalog_price_lists`, `kontor_catalog_prices`
  migrations; `CatalogItem`/`Category`/`PriceList`/`PriceListEntry` domain
  objects — the first to use the SDK's `Money` value object;
  `CatalogItemRepository` (implementing `RepositoryInterface`, registered
  for import rollback), `CategoryRepository`, `PriceListRepository`,
  `PriceRepository`; `PricingService` (quantity-tier + date-window price
  resolution); `Support\UnitOfMeasure`/`Support\TaxCode` known-code
  registries; real `ItemImportProvider`/`ItemExportProvider`;
  `CatalogHealthCheck`; permissions; translations (en/fr/de/es).

### Fixed

- Provider registration now supplies the required `catalog_item` key, and
  money hydration accepts native integer values returned by modern PDO.
