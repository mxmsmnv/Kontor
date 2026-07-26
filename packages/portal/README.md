# Kontor Portal

`kontor/portal` — customer login, quotations, invoices, payments, files,
profile. Second component of Stage 9. kontor.md doesn't give a detailed
portal specification — full gap-fill, this substage's milestones are the
six named in its own title. Depends on `kontor/core` **and**
`kontor/contacts`, `kontor/sales`, `kontor/invoices`, `kontor/payments`,
`kontor/files` — an aggregator over five sibling packages, reused
directly rather than duplicated (the same "genuine cross-package
dependency" precedent `kontor/projects`/`kontor/purchasing` already
established for their own "integration" milestones). None of those five
packages is ever modified by this one.

## A portal account is always one Contact

`kontor_portal_accounts` links a login (email + `password_hash`, PHP's
own `password_hash()`/`password_verify()`, no third-party auth library)
to exactly one `kontor/contacts` `Contact` (`contact_uid`). A shared
portal account across every contact at one company is out of scope for
this substage — see "Not in scope".

## Quotations/invoices: a read-only view over the sibling tables, not a modification of them

`CustomerQuotationRepository`/`CustomerInvoiceRepository` query
`kontor/sales`'s `kontor_sales_quotations` and `kontor/invoices`'s
`kontor_invoices` tables directly, scoped to `customer_type = 'contact'
AND customer_uid = :contactUid`, and hydrate the real
`Kontor\Sales\Domain\Quotation`/`Kontor\Invoices\Domain\Invoice` objects
(their constructors are public — the same hydration their own
repositories do internally). Neither sibling repository exposes a
"find by customer" method, and neither is retrofitted with one here —
Portal owns this query as its own repository instead, the same "never
modify an already-shipped package for a later consumer" discipline used
throughout this monorepo. Every lookup checks ownership
(`findOwned()`) — a customer can never see another customer's document.

## Payments: read-only history, no gateway

`CustomerPaymentService::paymentsForInvoice()` reuses
`kontor/payments`'s own `PaymentAllocationRepository::forDocument()` +
`PaymentRepository::find()` directly — a `Payment` has no `invoiceUid`
of its own, only its allocations link it to a document, so an invoice's
payment history is resolved the exact same two-step way `kontor/payments`
uses internally. Initiating a *new* payment (a card/bank gateway) is out
of scope — no payment gateway integration exists anywhere in this
monorepo yet.

## Files: the real download endpoint `kontor/files` deferred

`kontor/files`'s own README documents that wiring an HTTP endpoint to
verify a signed URL and stream the file back was left for "a later
stage." This substage *is* that later stage — but the endpoint is
customer-facing, not admin-facing, so it lives here rather than in
`kontor/files` (never modified). `CustomerFileService` signs its own URL
with `Kontor\Files\Infrastructure\Storage\SignedUrlSigner`, using the
same secret `kontor/files` uses (ProcessWire's own `config->authSalt`)
but pointed at Portal's own `/portal/files/download` route.
`PortalFileDownloadHandler::handle()` verifies the signature and expiry
and streams the file back via `StorageInterface`; `KontorPortal::hookFileDownload()`
is the thin `ProcessPageView::execute` hook wiring it to a real URL — the
same "pure core, thin I/O wrapper" split `kontor/api`/`kontor/graphql`
already use for their own real HTTP entry points.

## Profile: an explicit safe-field allowlist

`CustomerProfileService::update()` only allows changing
`firstName`/`middleName`/`lastName`/`phone`/`mobile`/`preferredLanguage`
on the linked Contact — not `status`, `assignedUserId`, `source`, or
anything else the staff-facing CRM can touch. An unlisted field is a
hard `InvalidArgumentException`, never silently ignored.

## Contents

- `migrations/` — `kontor_portal_accounts` (the "customer login"
  milestone's own table; standard columns in full).
- `src/Application/PortalAuthenticationService.php` — register/authenticate.
- `src/Infrastructure/Persistence/CustomerQuotationRepository.php` /
  `CustomerInvoiceRepository.php` — the "quotations"/"invoices" milestones.
- `src/Application/CustomerPaymentService.php` — the "payments" milestone.
- `src/Application/CustomerFileService.php` /
  `PortalFileDownloadHandler.php` — the "files" milestone, see above.
- `src/Application/CustomerProfileService.php` — the "profile" milestone.
- `src/Health/PortalHealthCheck.php` — flags a portal account whose
  linked contact has gone missing (e.g. via `kontor/contacts`'s own GDPR
  erasure), not just a count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/` (`PortalAccount`'s domain mutators,
`PortalFileDownloadHandler` against a fake `StorageInterface`) needs no
database and runs for real. `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise — the fifth
real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
`kontor/core`, after `kontor/api`, `kontor/graphql`, `kontor/marketplace`
and `kontor/mail`, exercising real cross-package data (creating
quotations/invoices/payments/files/contacts through their own owning
packages' repositories, then reading them back through Portal's own
customer-scoped view).

## Not in scope for this substage

No shared/company-wide portal accounts — a portal account is always one
Contact. No payment gateway integration — payments are read-only
history. No document line items on the quotation/invoice view — only
header-level fields (number, status, total, dates); a future enhancement
could reuse `kontor/sales`'s shared `DocumentLineRepository` the same way
`kontor/invoices` already does. No portal web UI or JSON API beyond the
one real HTTP endpoint (file download) — the same deferral every other
component's real UI has made, since none exists anywhere in this
monorepo yet; that one exception was built because serving actual bytes
requires a real HTTP handler and directly completes a gap `kontor/files`'
own README already flagged as deferred.
