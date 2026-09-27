# Testing Plan

## Scope

- Project: `Kontor`
- Classification: D (complex product/platform)
- Test owner: Kontor maintainers
- Supported ProcessWire versions: 3.0.240+
- Supported PHP versions: 8.2+
- Uninstall data policy: `preserve-user-data`

## Risk summary

Kontor stores organization-scoped customer, commercial, operational and
financial data across independently installable components. Its highest-risk
boundaries are cross-component state transitions, tenant and permission
isolation, irreversible financial history, private files, public API and portal
routes, async work, and external mail/payment/AI/webhook providers.

Most combinatorial coverage belongs in package unit, integration and contract
tests. Browser coverage is intentionally limited to representative complete
journeys and access isolation.

## Test commands

```bash
# PHP syntax for shipped PHP
find . -path './vendor' -prune -o -path '*/vendor' -prune -o \
  -name '*.php' -print0 | xargs -0 -n1 php -l

# Unit tests (DB-gated tests skip when the variables are absent)
vendor/bin/phpunit

# Integration and migration tests against a dedicated disposable database
KONTOR_TEST_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=kontor_test' \
KONTOR_TEST_DB_USER='kontor' KONTOR_TEST_DB_PASS='kontor' \
vendor/bin/phpunit --testsuite integration,migration

# Focused CRM Intake cross-package journey
KONTOR_TEST_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=kontor_test' \
KONTOR_TEST_DB_USER='kontor' KONTOR_TEST_DB_PASS='kontor' \
vendor/bin/phpunit tests/Integration/CRMIntakeJourneyTest.php
```

There is not yet a committed automated browser runner. Until one is added, the
critical journeys below are release-gating agent-led scenarios and their exact
environment and evidence must be recorded. A successful PHPUnit run is not an
E2E pass.

## Test environment

- Development site: a disposable ProcessWire 3.0.240+ installation; never a
  production site
- Database isolation: a unique disposable database or dedicated test tenant
- Administrator account: `E2E admin` fixture (password is environment-local)
- Member/editor accounts: `E2E member` and `E2E editor` fixtures with explicit
  Kontor permissions
- External service fakes: local mail catcher, demo payment provider, mock HTTP
  server, local/private filesystem adapter and deterministic AI provider
- Fixture prefix: `E2E`

## ProcessWire boundary coverage

- [ ] Module discovery and metadata
- [ ] Fresh Core install followed by dependency-ordered component install
- [ ] Configuration defaults and save
- [ ] Missing and incompatible dependencies
- [ ] Public APIs and documented hooks
- [ ] Permissions and organization isolation
- [ ] Data save and reload
- [ ] Upgrade from every supported prior release
- [ ] Uninstall preserves business data
- [ ] Reinstall reconnects preserved data

## Critical automated journeys

### Journey 1: qualification to deal

- Role: CRM editor
- Starting state: Contacts, CRM and CRM Intake installed; an active intake
  profile; a default deal pipeline with an open stage
- Actions: create a contact, create a linked lead, capture required intake
  answers, qualify the lead and convert it to a deal
- Expected UI result: contact, lead and deal workspaces show the configured
  human-readable fields and the conversion ends on the new deal
- Expected stored result: one response per entity; fields targeting deals are
  copied, contact-only fields are not; the bound source remains consistent
- Access/security assertion: a user without CRM Intake administration cannot
  manage the profile; a user without deal creation cannot convert
- Cleanup: remove the `E2E` records or discard the database

### Journey 2: representative order-to-cash

- Role: sales/finance editor
- Starting state: the connected demo dependencies are installed with demo
  providers only
- Actions: customer to quotation, order, invoice, demo payment and allocation
- Expected UI result: every handoff links to the resulting record
- Expected stored result: totals and state transitions agree across packages
- Access/security assertion: ordinary users cannot perform restricted finance
  corrections or view another organization
- Cleanup: discard the database

### Journey 3: settings migration without business data

- Role: Kontor administrator
- Starting state: Settings and CRM Intake installed; profile and captured
  answers exist
- Actions: export, validate-only preview and confirmed import into another
  disposable tenant
- Expected UI result: the profile is previewed and imported
- Expected stored result: profile definitions transfer; captured answers,
  credentials and secrets do not
- Access/security assertion: non-administrators are denied
- Cleanup: discard both tenants/databases

## Agent-led release scenarios

### Administrator session

- [ ] Install Core and selected components in dependency order
- [ ] Configure a CRM Intake profile and inspect health/components/settings
- [ ] Verify uninstall warning and preserved-data policy without using live data

### Member/editor session

- [ ] Complete the qualification-to-deal journey
- [ ] Confirm permitted navigation excludes unavailable or forbidden components

### Anonymous session

- [ ] Kontor admin and API routes deny access without leaking record data

### Cross-role or multi-user scenario

- [ ] Administrator configures a profile; editor uses it; restricted member
  cannot manage it or convert the lead

### Presentation

- [ ] Representative desktop viewport
- [ ] Representative mobile viewport
- [ ] Keyboard/accessibility smoke
- [ ] Light and dark admin themes
- [ ] Browser console and failed network requests inspected

## Failure paths

- [ ] Invalid and missing intake values
- [ ] Unauthenticated access
- [ ] Unauthorized role and cross-organization identifiers
- [ ] CSRF failure on every mutation
- [ ] Duplicate submission or webhook replay
- [ ] External timeout/failure
- [ ] Retry and idempotency
- [ ] Empty state
- [ ] Large or boundary data state
- [ ] Logs contain no secrets or unnecessary personal data

## External services

| Service | Test substitute | Live test policy |
|---|---|---|
| Mail | local catcher/in-memory transport | no live delivery in ordinary tests |
| Payments | demo gateway | explicitly authorized test credentials only |
| Webhooks/API clients | local mock HTTP server | no production endpoints |
| AI | deterministic fake provider | explicitly authorized test provider only |
| Private files/object storage | disposable local or S3-compatible store | no production buckets |

## Cleanup

- [ ] Test settings restored
- [ ] `E2E` users/data removed or the disposable database discarded
- [ ] Queue jobs completed or removed safely
- [ ] Browser workers, servers and watchers stopped
- [ ] No real external side effects occurred

## Release evidence

Record the commit, installed module/component versions, ProcessWire/PHP/database
versions, profile (`change validation`, `pull request`, `nightly` or `release
candidate`), commands and results, roles/sessions, completed journeys, defects,
blocked areas, cleanup state and failure artifacts.
