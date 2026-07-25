# Kontor Payments

`kontor/payments` — payments, allocations, partial payments, and
reversals. Seventh business component, closing out Stage 4 (Sales and
finance-lite, kontor.md#36). Depends on `kontor/invoices`, not just
`kontor/core`.

## The first cross-package write

`kontor/invoices`' own README says its `paid`/`due` fields "stay in sync…
but nothing in this package ever moves `paid` off zero — that's Payments'
job (Substage 4.4)." This package is that job:
`PaymentAllocationService` reuses `Kontor\Invoices\Infrastructure\
Persistence\InvoiceRepository` directly to read and rewrite an invoice's
`paid`/`due`/`status` whenever a payment is allocated to or un-allocated
from it — the first place in this monorepo where one business component
mutates another's records rather than just referencing them by uid.

## Contents

- `migrations/` — `kontor_payments` (kontor.md#15.5) and
  `kontor_payment_allocations` (kontor.md#15.6, followed exactly including
  its lack of `created_at`/`updated_at`/`version`/`archived_at` — a lighter
  junction table than the rest of the schema).
- `src/Domain/Payment.php` — status is `'draft'` → `'confirmed'` →
  `'reversed'`, matching the `kontor-payments-payment-edit-draft`
  permission (kontor.md#19.7): a draft is still editable, confirming makes
  it allocatable.
- `src/Application/PaymentWorkflowService.php` — the "payments" milestone's
  own lifecycle: `confirm()` (assigns a number via `SequenceService`,
  `PMT-` prefix) and `reversePayment()` — the payment-level half of the
  "reversals" milestone, which reverses every active allocation the
  payment funded before marking the payment itself reversed.
- `src/Application/PaymentAllocationService.php` — the "allocations" and
  "partial payments" milestones, and the other half of "reversals":
  `allocate()` guards against exceeding both the payment's own remaining
  balance and the invoice's remaining due amount before creating a
  `kontor_payment_allocations` row and recomputing the invoice;
  `reverseAllocation()` un-reverses one allocation and recomputes again.
  Recomputation is always **from scratch** — summing every non-reversed
  allocation for the invoice via `totalAllocatedForDocument()` — rather
  than incrementally adding/subtracting, so `paid`/`due`/`status` can never
  drift no matter how many allocate/reverse calls happened before.
- Only `document_type = 'invoice'` is implemented — the allocations
  table's `document_type`/`document_uid` pair is polymorphic like
  `kontor_document_lines`', but invoices are the only document type this
  monorepo can allocate a payment against today.

## Invoice status recomputation rule

Given an invoice's `total` and the sum of its non-reversed allocations
(`paid`):

- `paid == 0` → back to `'sent'`, or `'overdue'` if `dueDate` has passed
  (there's no record of whether it was `'issued'` vs `'sent'` before the
  first allocation, so reversing to zero always lands on `'sent'`/
  `'overdue'`, never `'issued'`).
- `paid >= total` (i.e. `due <= 0`) → `'paid'`, `paidAt` set.
- otherwise → `'partially_paid'`.

This is kontor.md diagram 17.2's `Sent`/`Overdue` ↔ `PartiallyPaid` ↔
`Paid` transitions, driven purely by allocation totals.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/PaymentTest.php` needs no database and runs for real.
Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Not in scope for this substage

`kontor-payments-refund-create` is registered as a permission
(kontor.md#19.7) but no refund flow is built — a refund implies moving
money back out through an external payment gateway, which no component in
this monorepo integrates with yet; `reversePayment()`/`reverseAllocation()`
cover the purely-internal "undo this allocation" case the "reversals"
milestone actually asks for. No order/quotation allocation support (only
invoices). No admin UI/API endpoints.
