# Kontor Expenses

`kontor/expenses` — expenses, categories, receipts, and approvals. Third
component of Stage 6. No dedicated schema section, permission list, or
workflow diagram in kontor.md for this substage — full gap-fill, same
situation Tasks/Collaboration/Dashboard/Reports/Purchasing were in.
Depends only on `kontor/core`.

## Receipts and supplier links stay loose references

`receipt_file_uid` points at a `kontor/files` row and `supplier_uid` at a
`kontor/purchasing` supplier, but neither is a hard dependency — both stay
loose references (kontor.md#10.7: "prefer stable IDs over direct foreign
keys"), the same looseness `kontor_document_lines.item_uid` already has
toward `kontor/catalog`. This package doesn't call into either component;
it just stores the uid.

## Contents

- `migrations/` — `kontor_expense_categories` (a real table, unlike
  `kontor/catalog`'s `UnitOfMeasure`/`TaxCode` fixed in-memory registries —
  categories here are organization-specific and user-managed) and
  `kontor_expenses`.
- `src/Application/ExpenseWorkflowService.php` — the "approvals" milestone:
  a single-approver status workflow, not a multi-step chain (nothing in
  this substage's milestones asks for one) — `draft` → `submitted` →
  `approved`/`rejected` → (if approved) `reimbursed`, or `cancelled` any
  time before a decision is made. `reject()` requires a non-blank reason.
- `src/Health/ExpensesHealthCheck.php` — checks a real invariant (no
  expense has both `approved_at` and `rejected_at` set — `approve()`/
  `reject()` are mutually exclusive terminal decisions from `submitted`),
  not just a count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/ExpenseTest.php` needs no database and runs for real.
Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The main Kontor Process module now exposes category management, expense
capture, status views, and the full single-approver lifecycle from draft
through reimbursement. Supplier and receipt references remain optional and
loose, matching the package boundary.

## Not in scope for this substage

No multi-step/multi-approver approval chains. No spending limits or
per-category budgets. No API endpoints — receipt upload goes through
`kontor/files` directly (storing the resulting uid here), not
through this package.
