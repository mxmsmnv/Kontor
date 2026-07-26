# Changelog

All notable changes to `kontor/expenses` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- First admin vertical: category creation, organization-scoped status views,
  expense capture, and permission-gated submit, approve/reject, cancel, and
  reimburse actions.
- Initial alpha (Substage 6.3): `kontor_expense_categories` and
  `kontor_expenses` migrations (full schema gap-fill, no dedicated spec
  section); `ExpenseCategory`/`Expense` domain objects;
  `CategoryRepository`/`ExpenseRepository` (both implementing
  `RepositoryInterface`); `ExpenseWorkflowService` — the "approvals"
  milestone as a single-approver status workflow
  (`submit()`/`approve()`/`reject()` with a required reason/`reimburse()`/
  `cancel()`); `ExpensesHealthCheck` (checks that no expense is both
  approved and rejected, not just a count); permissions; en/fr/de/es
  translations. Third component of Stage 6.
