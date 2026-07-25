# Changelog

All notable changes to `kontor/payments` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 4.4): `kontor_payments` and
  `kontor_payment_allocations` migrations (kontor.md#15.5–15.6); `Payment`
  (draft/confirmed/reversed) and `PaymentAllocation` domain objects;
  `PaymentRepository` (implementing `RepositoryInterface`) and
  `PaymentAllocationRepository`; `PaymentWorkflowService`
  (`confirm()`/`reversePayment()`); `PaymentAllocationService`
  (`allocate()`/`reverseAllocation()` — the first cross-package write in
  this monorepo, driving `kontor/invoices`' `Invoice.paid`/`due`/`status`,
  which that package's own README left "not actively driven" pending this
  substage); `PaymentsHealthCheck`; permissions (kontor.md#19.7); en/fr/de/es
  translations. Closes out Stage 4 (Sales and finance-lite) per spec
  section 36.
