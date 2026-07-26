# Kontor Sales

`kontor/sales` — quotations, orders, a shared document-lines table,
quotation-to-order conversion, and status workflows. Fourth business
component, and the leanest dependency graph of any built so far: only
`kontor/sdk` + `kontor/core`. No import/export/search/reports milestone
this substage (unlike Contacts/Catalog/CRM) — `customer_type`/`customer_uid`
are a polymorphic reference (kontor.md#10.7: "prefer stable IDs over direct
foreign keys"), not a hard dependency on `kontor/contacts`.

## Contents

- `migrations/` — `kontor_sales_quotations`, `kontor_sales_orders`
  (kontor.md#15.1–15.2), `kontor_document_lines` (kontor.md#15.4 — shared
  by quotations and orders now, and by invoices later, Substage 4.3, via
  `document_type`/`document_uid` rather than one lines table per document).
- `src/Domain/DocumentLine.php` — the "document lines" milestone includes
  real subtotal/discount/tax/total calculation, not just storage:
  quantity × unit price, minus a percentage or fixed discount, then tax on
  the discounted subtotal.
- `src/Application/QuotationWorkflowService.php` /
  `OrderWorkflowService.php` — the "status workflows" milestone: quotation
  `draft → issued → sent → accepted/rejected/expired/cancelled`, order
  `pending → confirmed → completed`/`cancelled`. `workflow_state`
  (kontor.md#15.1) is reserved for the future KontorWorkflow component
  (spec section 18) — this substage's transitions are enforced on `status`
  alone.
- `src/Application/QuotationToOrderConversionService.php` — the
  "conversion" milestone (kontor.md diagram 17.1: "Accepted? → Yes: Create
  sales order"): copies every quotation line into new order lines (new
  uids, same pricing) rather than referencing the originals, so the order
  survives even if the quotation is later archived.
- Quotation issuance resolves the active `quotation.standard` Documents
  template with language fallback, writes its exact UID and immutable render
  snapshot into Sales, and stores the resulting confidential PDF through
  Files. The issued quotation links back to that historical template and
  private file even after newer template versions are published.

## Filled a third Core gap

`kontor_sequences` (kontor.md#11.9) has existed since Substage 1.2, but
nothing ever built a service for it — quotations/orders needing real
document numbers made `kontor/sales` its first consumer. Added
`Kontor\Core\Infrastructure\Persistence\SequenceService` to `kontor/core`:
atomic `next()` locks the sequence row for the duration of an increment
(same technique as `kontor/queue`'s `JobRepository::reserveNext()`), and a
sequence's prefix/suffix/padding/reset policy are fixed by whichever call
creates it — later calls can't accidentally change them.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages.

## Not in scope for this substage

`payment_status`/`fulfillment_status` on orders are simple descriptive
fields, not actively driven by Sales — their real lifecycle belongs to
Payments (Substage 4.4) and Inventory (Substage 6.1). No admin UI/API
endpoints beyond the shared ProcessKontor workflow. Order `snapshot_json`
remains reserved for a later order-issuance workflow; quotation snapshots
are populated at issue time.
