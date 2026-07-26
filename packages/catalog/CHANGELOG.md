# Changelog

All notable changes to `kontor/catalog` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Category status filtering for active and archived Catalog views.
- Item status filtering for active and archived Catalog views.
- Explicit Uncategorized filtering for active and archived Catalog items.
- Direct new-tier flow from a Catalog item with the item preselected in the
  pricing form.
- Organization-scoped price-tier lookup across all price lists for a Catalog
  item.
- Organization-scoped Catalog item filtering and active-item usage counts by
  category.
- Transactional, tenant-scoped bulk activation and deactivation for up to
  100 price lists.
- Transactional, tenant-scoped bulk archive and restore operations for up to
  100 Catalog categories.
- Transactional price-list duplication that copies every quantity tier into
  a new inactive draft.
- Organization-scoped aggregate Catalog summary counts for dashboards.
- Safe Catalog item duplication with fresh identity, cleared SKU/barcode,
  copied commercial data, and inactive status.
- Transactional, tenant-scoped bulk archive and restore operations for up to
  100 Catalog items.
- Catalog items in global search, covering localized titles/descriptions,
  SKU, and barcode with direct admin links and exact-identifier ranking.
- Organization-scoped Catalog backup/restore across items, categories, price
  lists, and price tiers, registered for protected imports.
- Organization-scoped active-item usage counts for unit and tax references.
- Price-list search/pagination helpers and price-tier list/count/delete/replace
  persistence operations for the admin pricing workspace.
- Organization-scoped catalog listing, search, type filters, archived views,
  exact counts, and offset pagination for the admin workspace.
- Searchable and paginated category queries with active/archive counts,
  deterministic ordering, required lookup, and restore support.
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

- Catalog exports now use flat localized fields compatible with Catalog
  imports and include all item price/currency columns.
- Catalog item exports now yield associative rows only, without duplicate
  numeric PDO keys.
- Provider registration now supplies the required `catalog_item` key, and
  money hydration accepts native integer values returned by modern PDO.
