# Kontor Ledger

`kontor/ledger` — double-entry foundations, chart of accounts. Fourth
component of Stage 9 (part of Substage 9.4, alongside `kontor/germany`).
kontor.md doesn't give a detailed ledger specification — full gap-fill.
Depends only on `kontor/core`; deliberately has no knowledge of any
specific country's chart of accounts or fiscal rules — that's
`kontor/germany`'s (and any future country package's) own job, built on
top of this one.

## The balance check is what makes this double-entry at all

`LedgerBalanceValidator::validate()` is the one rule this whole package
exists to enforce: an entry's lines must sum to zero net (debits minus
credits) per currency, at least two lines, no line carrying both a debit
and a credit, no negative amounts. `LedgerEntryService::record()` runs
this check — and confirms every referenced account actually belongs to
the calling organization — **before** writing anything; a failure leaves
no partial entry behind. `LedgerHealthCheck` re-derives the same check
directly from the database as a defense-in-depth measure, catching any
corruption a bug (or a direct SQL write bypassing the service layer)
could otherwise introduce silently.

## Pure vs DB-touching split

`LedgerBalanceValidator` (the balance rule) and `AccountBalanceCalculator`
(computing an account's running balance from its normal side) are both
pure — no database — the same "pure vs DB-touching split"
`kontor/entities`'s `EntityViewService` and `kontor/marketplace`'s
`AdvisoryService` already use, so the actual business rules run as real
unit tests, not just DB-gated integration tests.

## Contents

- `migrations/` — `kontor_ledger_accounts` (chart of accounts, standard
  columns in full), `kontor_ledger_entries` / `kontor_ledger_lines`
  (append-only once recorded — a correction is a new, reversing entry,
  never an edit, the same reasoning `kontor/automation`'s execution log
  already uses for its own append-only table).
- `src/Application/ChartOfAccountsService.php` — the "chart of accounts"
  milestone.
- `src/Application/LedgerEntryService.php` /
  `LedgerBalanceValidator.php` — the "double-entry foundations"
  milestone.
- `src/Application/AccountBalanceService.php` /
  `AccountBalanceCalculator.php` — an account's running balance,
  normal-side aware (debit-normal for asset/expense, credit-normal for
  liability/equity/revenue).
- `LedgerEntryRepository::findByReference()` — resolves an immutable
  posting by its business reference, allowing integrations to remain
  idempotent without editing accounting history.
- `src/Health/LedgerHealthCheck.php` — see above.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/` (`LedgerBalanceValidator`'s every rule,
`AccountBalanceCalculator`, `Account`'s domain logic) needs no database
and runs for real. `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise — the seventh
real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
`kontor/core`.

## Not in scope for this substage

No draft/void workflow for entries — every entry is immutable and final
once recorded; a correction is a new, reversing entry. No financial
statements (trial balance, P&L, balance sheet reports) — those are a
future `kontor/reports` provider built on top of `AccountBalanceService`.
No standard/seeded chart of accounts of its own — country packages
(`kontor/germany`) seed their own localized chart through
`ChartOfAccountsService`.
