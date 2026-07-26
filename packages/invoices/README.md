# Kontor Invoices

`kontor/invoices` — invoices, the issue workflow, document numbering, the
overdue state, and credit notes. Sixth business component (Substage 4.3).
Unlike Sales/Documents, this one has a real hard dependency beyond
`kontor/core`: `kontor/sales`, because it reuses `kontor_document_lines`
and Sales' `DocumentLine`/`DocumentLineRepository` classes directly rather
than duplicating that table and those classes into a parallel copy — the
Sales README predicted this exact reuse back in Substage 4.1.

Mail is optional. When installed, ProcessKontor delivers an issued invoice
through an active shared mailbox, records the outbound message, and creates a
`mail_link` relation back to the invoice. Delivery defaults to a safe
simulation until the transport is deliberately enabled.

## A second schema gap, filled inside this package

kontor.md#15.3 gives `kontor_invoices` a column list, and kontor.md#19.6 /
diagram 17.2 both clearly treat credit notes as a distinct, permissioned
concept ("Paid --> Credited: credit note") — but section 15 never gives
credit notes their own table or column. This package's migration adds two
columns beyond the spec's list: `kind` (`'invoice'` | `'credit_note'`) and
`credited_invoice_uid`. A credit note is just another `kontor_invoices` row
pointing back at the invoice it credits — it reuses the exact same
numbering, `kontor_document_lines` and (once wired) `kontor/documents`
rendering machinery as an ordinary invoice, rather than a parallel
  `kontor_credit_notes` table duplicating all of that.
- Issuing an invoice resolves `invoice.standard`; issuing a credit note
  resolves `credit_note.standard`. Both persist the exact Documents template
  UID and immutable snapshot in `kontor_invoices`, then store a confidential,
  entity-bound PDF through Files in the same database transaction.

## Contents

- `migrations/` — `kontor_invoices` (kontor.md#15.3 + the `kind`/
  `credited_invoice_uid` gap-fill above).
- `src/Application/InvoiceWorkflowService.php` — the "issue workflow",
  "numbering", "overdue state" and "credit notes" milestones:
  - `issue()` — draft → issued, computes totals from lines
    (`kontor_document_lines` where `document_type = 'invoice'`), assigns a
    number via `SequenceService` (`INV-` prefix, yearly reset, same
    locking technique as Sales' quotation/order numbers).
  - `send()` — issued → sent.
  - `cancel()` — allowed from draft/issued/sent only, matching diagram
    17.2's "controlled cancellation" transitions (not from overdue/paid/
    credited).
  - `markOverdue()` / `sweepOverdue()` — diagram 17.2 only draws
    Sent → Overdue (not Issued → Overdue), so that's the only transition
    implemented; `sweepOverdue()` finds every sent, past-due invoice in an
    organization in one pass. There's no scheduler component yet
    (Stage 7), so this is a plain method — see "Not in scope" below.
  - `issueCreditNote()` — a **full** credit against an already-issued
    invoice: copies every line with quantity negated (so subtotal/tax/
    total come out negative from the normal `DocumentLine` calculation),
    numbers it via a separate `credit_note` sequence (`CN-` prefix), and
    moves the original straight to `'credited'`. Guarded against crediting
    a draft, an already-credited invoice, or a credit note itself.
- `src/Domain/Invoice.php` — `paid`/`due` fields exist per kontor.md#15.3
  and stay in sync (`due = total - paid`) whenever totals are recomputed,
  but nothing in this package ever moves `paid` off zero — that's
  Payments' job (Substage 4.4).

## Payment-driven transitions are out of scope here

Diagram 17.2's `PartiallyPaid`/`Paid` states (and the `Paid --> Credited`
precondition specifically) depend on payments existing, which they don't
yet. `issueCreditNote()` is relaxed to accept any issued/sent/overdue/paid
source rather than requiring `Paid`, the same way Sales left
`payment_status` "not actively driven" pending its own dependency
(Payments, Substage 4.4). Once Payments exists and actually drives invoices
to `Paid`, this restriction can be tightened without changing the schema.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/InvoiceTest.php` needs no database (pure totals/status
logic) and runs for real. Everything under `tests/Integration/` needs real
MySQL (see `../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Not in scope for this substage

No order→invoice conversion service (unlike Sales' quotation→order
conversion) — not a listed Substage 4.3 milestone; `Invoice::create()`
accepts an optional `orderUid` for a caller to link one manually.
Partial credit notes aren't built — only full credits, since partial
credit notes aren't a listed milestone either. No scheduler/cron wiring for
`sweepOverdue()` — that arrives with Stage 7's automation component; until
then it's a method a manual CLI invocation or an external cron entry can
call. No admin UI/API endpoints beyond the shared ProcessKontor workflow.
`template_uid` and `snapshot_json` are populated when invoices and credit
notes are issued.
