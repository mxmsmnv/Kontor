# Changelog

All notable changes to `kontor/germany` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Admin localization workbench for German VAT-ID checksum validation,
  SKR03 account readiness and seeding, and generated XRechnung XML previews.
- Initial alpha (Substage 9.4): `GermanTaxIdValidator` (validates a
  German USt-IdNr. via the published BZSt checksum algorithm, verified
  against a real, publicly known VAT ID); `GermanyLocalizationProvider`
  (implements `kontor/sdk`'s new `LocalizationProviderInterface`,
  registered as the `"localization.de"` capability in Core's
  `CapabilityRegistry`); `StandardChartOfAccountsSeeder` (seeds an
  illustrative subset of the standard German SKR03 chart of accounts
  through `kontor/ledger`'s own `ChartOfAccountsService`);
  `XRechnungFormatter` + `LocalizedInvoiceInput`/`LocalizedPartyInput`/
  `LocalizedLineItemInput` (the "country-specific document formats"
  milestone — a simplified, illustrative UBL-inspired XML export using
  PHP's own `DOMDocument`, no third-party library; ZUGFeRD is declared
  supported but not implemented). Fifth and final component of Stage 9
  (Advanced capabilities), and of the entire kontor.md#36 build plan.

### Fixed

- Standard chart seeding preflights incompatible account-code conflicts and
  can be repeated safely without duplicating already-compatible accounts;
  compatible archived accounts are restored during setup.
