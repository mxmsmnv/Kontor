# Kontor Germany

`kontor/germany` — localization contracts, Germany package,
country-specific document formats. Fifth and final component of Stage 9,
and of the entire kontor.md#36 build plan. Depends on `kontor/core`,
`kontor/sdk`, and `kontor/ledger` (to seed its own localized chart of
accounts).

## Localization contracts: an interface, never a hard dependency

`Kontor\SDK\Contracts\LocalizationProviderInterface` (kontor.md#5.4)
lives in `kontor/sdk` — this package is its first consumer.
`KontorGermany::init()` registers `GermanyLocalizationProvider` as the
`"localization.de"` capability in Core's `CapabilityRegistry`, the same
inverted-dependency shape `kontor/cache`/`kontor/files` already use for
their own capabilities. Neither Core nor any business package ever
depends on this package's classes directly — a future `kontor/usa`,
`kontor/uk`, etc. would register their own `"localization.{code}"`
capability the same way.

## Germany package

`GermanTaxIdValidator` validates a German USt-IdNr. (`DE` + 9 digits)
using the published ISO 7064 MOD 11,10-style checksum algorithm the BZSt
itself uses — verified in this package's own tests against a real,
publicly published VAT ID (SAP SE's own). This checks the number's
internal consistency only, not whether it's actually registered with the
tax authority (that needs an external lookup, e.g. the EU VIES service,
deliberately out of scope here).

`StandardChartOfAccountsSeeder` seeds a small, illustrative subset of the
standard German SKR03 chart of accounts through `kontor/ledger`'s own
`ChartOfAccountsService` — not the real SKR03 (several hundred accounts,
and not freely redistributable in full), just enough top-level accounts
to prove a country package actually integrates with the ledger
foundations rather than only declaring capability metadata.

## Country-specific document formats

`XRechnungFormatter` produces a UBL-inspired XML structure carrying
XRechnung's core elements (invoice number, dates, seller/buyer party,
line items, tax and monetary totals) using PHP's own `DOMDocument` — no
third-party XML/UBL library, the same "avoid a heavy dependency for a
narrow need" call `kontor/documents` already made for its own template
engine. It operates on `LocalizedInvoiceInput` — a generic "invoice-like"
data contract deliberately decoupled from `kontor/invoices`'s own
`Invoice` domain object, so this package doesn't need to know any
particular business component's internal shape; any caller builds one
from whatever data it already has.

This is a **simplified, illustrative subset** of the real
XRechnung/EN16931 semantic data model, not a certified-compliant
implementation — see "Not in scope" below.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Everything under `tests/Unit/` needs no database —
`GermanTaxIdValidator`, `GermanyLocalizationProvider`, and
`XRechnungFormatter` (asserted via `DOMXPath` against the generated XML)
all run for real. `tests/Integration/StandardChartOfAccountsSeederTest`
needs real MySQL (see `../../docker-compose.test.yml`) and is skipped
otherwise — the eighth and final real consumer of
`Kontor\Core\Testing\DatabaseTestCase` in this entire build plan.

## Not in scope for this substage

Full XRechnung/EN16931 compliance — the complete CIUS schema and
Schematron business-rule validation (hundreds of rules) is well beyond
this substage's "foundations" scope. ZUGFeRD itself (the PDF+embedded-XML
sibling format) is declared as a supported format but not implemented —
it would combine this same XML with `kontor/documents`'s existing PDF
renderer, which this package doesn't depend on. No live VAT-ID
registration lookup (EU VIES). No other country packages (USA, UK,
France, Spain, …) — this substage's own milestone is "Germany package,"
singular; further countries are each their own future package following
the same `LocalizationProviderInterface` contract.
