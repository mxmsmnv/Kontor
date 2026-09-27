# Changelog

All notable changes to `kontor/portal` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- Portal account persistence and customer profile updates now reject contacts
  from another organization before any mutation; health checks also report
  legacy accounts with missing or cross-organization contacts.

### Added

- First ProcessKontor admin vertical: account provisioning and lifecycle,
  credential verification, safe profile editing, plus customer-scoped
  quotation, invoice, payment, and file previews.
- Initial alpha (Substage 9.2): `kontor_portal_accounts` migration
  (kontor.md, full gap-fill); `PortalAccount` domain +
  `PortalAccountRepository`; `PortalAuthenticationService` (the "customer
  login" milestone — PHP's own `password_hash()`/`password_verify()`,
  never a ProcessWire staff user account); `CustomerQuotationRepository`/
  `CustomerInvoiceRepository` (the "quotations"/"invoices" milestones —
  read-only, ownership-checked views over `kontor/sales`'s and
  `kontor/invoices`'s own tables, hydrating their real Domain objects
  directly rather than a parallel DTO); `CustomerPaymentService` (the
  "payments" milestone, reusing `kontor/payments`'s own
  `PaymentAllocationRepository`/`PaymentRepository` directly);
  `CustomerFileService` + `PortalFileDownloadHandler` (the "files"
  milestone — the real signed-URL-verifying download endpoint
  `kontor/files`'s own README flagged as deferred, built here since it's
  customer-facing rather than admin-facing) +
  `KontorPortal::hookFileDownload()` (the real `/portal/files/download`
  HTTP entry point); `CustomerProfileService` (the "profile" milestone,
  restricted to a safe field allowlist); `PortalHealthCheck` (flags a
  portal account whose linked contact has gone missing); permissions;
  en/fr/de/es translations. Second component of Stage 9 (Advanced
  capabilities); the first package in this monorepo to depend on five
  sibling packages at once.
