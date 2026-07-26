# Kontor Catalog

`kontor/catalog` — items (products and services), categories, price lists,
units of measure and tax code references. Second business component, same
"consumes Core's infrastructure rather than providing a capability"
structure as `kontor/contacts`.

## What the spec doesn't define, and how the gaps were filled

Spec section 14 only defines three tables: items, price lists, and price
list items. Two Substage 3.2 milestones — "categories" and "units" — have
no corresponding table:

- **Categories**: `kontor_catalog_items.category_uid` presupposes a
  category entity with its own uid, so `kontor_catalog_categories` was
  added the same way `kontor_extensions` filled the "tags" gap for
  Contacts — standard columns (kontor.md#10.3/10.4), hierarchical via a
  self-referencing `parent_uid`.
- **Units** and **tax references**: `unit_code`/`tax_code` are plain
  strings on the item row, not links to their own entities — so
  `Support\UnitOfMeasure` and `Support\TaxCode` are lightweight,
  instance-based known-code registries (pcs/kg/hour/... and
  standard/reduced/zero/exempt/...), not repositories. Actual tax *rates*
  are a localization concern (spec section 5.4: KontorGermany, KontorUSA,
  ...) — out of scope here, this only validates that a code is a
  recognized category.

## Contents

- `migrations/` — `kontor_catalog_items` (kontor.md#14.1 — "products" and
  "services" are both rows here, distinguished by `item_type`, not
  separate tables; no `deleted_at` column, unlike Contacts, followed
  exactly), `kontor_catalog_categories`, `kontor_catalog_price_lists`
  (kontor.md#14.2), `kontor_catalog_prices` (kontor.md#14.3 — no `uid` of
  its own, like `kontor_contact_company`).
- `src/Domain/CatalogItem.php` — the first domain entity in this codebase
  to actually use the SDK's `Money` value object for real (Contacts had no
  money fields); `title`/`description` are locale => text arrays
  (kontor_catalog_items stores them as `title_json`/`description_json`),
  unlike Contact's single `display_name` string.
- `src/Infrastructure/Persistence/CatalogItemRepository.php` — implements
  `RepositoryInterface`, registered into `RepositoryRegistry` for import
  rollback, same pattern as `ContactRepository`.
- `src/Application/PricingService.php` — the concrete payoff for "price
  lists": resolves the price a given item/quantity/date actually pays,
  picking the highest quantity-break tier the quantity qualifies for
  among entries valid on that date.
- `src/Infrastructure/Import/ItemImportProvider.php` — CSV/XLSX rows are
  flat, so multi-language title/description come in as `title_en`,
  `title_fr`, etc. (one column per locale) rather than a nested JSON cell.
- `src/Infrastructure/Search/CatalogItemSearchProvider.php` — organization-
  scoped global search across localized titles/descriptions, SKU, and barcode,
  with exact identifiers ranked first.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages.

## Admin workspace

The host `ProcessKontor` module provides item, category, price-list, price-tier,
reference, import/export, backup, and global-search routes for Catalog. Item
forms expose localized item titles/descriptions and category names for English,
French, German, and Spanish, with the organization's default language required.
The package itself remains UI-framework agnostic.

Category import/export and REST API endpoints remain outside this substage.
