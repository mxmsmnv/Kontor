# Changelog

All notable changes to `kontor/ledger` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 9.4): `kontor_ledger_accounts`,
  `kontor_ledger_entries`, `kontor_ledger_lines` migrations (kontor.md,
  full gap-fill; entries/lines are append-only, standard columns apply
  in full to accounts); `Account`/`LedgerEntry`/`LedgerLine` domain
  objects; `AccountRepository`/`LedgerEntryRepository`/
  `LedgerLineRepository`; `ChartOfAccountsService` (the "chart of
  accounts" milestone); `LedgerBalanceValidator` + `LedgerEntryService`
  (the "double-entry foundations" milestone — every entry's lines must
  actually balance, per currency, before anything is persisted, pure
  validation logic split out for real unit testing);
  `AccountBalanceCalculator` + `AccountBalanceService` (an account's
  running balance, normal-side aware); `LedgerHealthCheck`
  (re-derives every entry's balance directly from the database as
  defense in depth); permissions; en/fr/de/es translations. Fourth
  component of Stage 9 (Advanced capabilities).
