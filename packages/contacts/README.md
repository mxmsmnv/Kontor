# Kontor Contacts

`kontor/contacts` — the first business component: contacts, companies,
addresses, contact-company memberships, tags, duplicate detection, and
import/export. Everything before this stage was platform infrastructure
(Core, SDK, Queue, Files, Cache, Search); this is the first component that
actually stores and manages business data.

Depends on `kontor/core` (persistence, registries), `kontor/search`
(federated search), and `kontor/api` for the registered `/contacts`
resource. Contacts consumes those platform extension points rather than
building parallel infrastructure.

## Contents

- `migrations/` — `kontor_contacts`, `kontor_companies`, `kontor_addresses`,
  `kontor_contact_company` (kontor.md#12.1–12.4). The membership table has
  no `uid`/`created_at` columns because the spec's own schema doesn't
  define them there — followed exactly rather than "improved".
- `src/Domain/` — `Contact`, `Company`, `Address`,
  `ContactCompanyMembership`. Plain data holders with a `::create()`
  factory; `Contact::composeDisplayName()` builds a display name from
  first/middle/last when none is given.
- `src/Infrastructure/Persistence/` — `ContactRepository` and
  `CompanyRepository` implement the SDK's `RepositoryInterface` (not just
  natural methods) specifically so they can register into
  `RepositoryRegistry` for `ImportManager::rollback()` (Substage 1.5).
  `archive()` (reversible) and `delete()` (a GDPR/legal erasure marker,
  kontor.md section 33) are distinct — the schema has both `archived_at`
  and `deleted_at` for exactly this reason.
- `src/Application/TagService.php` — the "tags" milestone, built on Core's
  new `ExtensionRepository` (`kontor_extensions`) rather than a dedicated
  table: tags are a small per-entity label set, exactly what
  `extension_key`/`value_json` already models.
- `src/Application/ContactDuplicateDetector.php` — exact email/phone
  matching within an organization; used standalone and by
  `ContactImportProvider::findExisting()`.
- `src/Infrastructure/Import/`, `src/Infrastructure/Export/` — real
  `ImportProviderInterface`/`ExportProviderInterface` implementations, the
  first actual consumers of Substage 1.5's engine. Validation errors are
  translation-key codes (`contact.email.invalid`, not English sentences)
  resolved via `resources/translations/{lang}/messages.json`.
- Search: registers `SqlFullTextSearchProvider` instances (from
  `kontor/search`, Substage 2.4) against `kontor_contacts`/`kontor_companies`'s
  own `FULLTEXT` indexes — no new search code needed, just configuration.
- API: registers `ContactResource` into the shared API registry. The real
  `/api/kontor/v1/contacts` endpoint supports organization-scoped list/find/
  create/update/soft-delete, pagination, `filter[query]`, `filter[status]`,
  sparse fields, and the standard bearer-token/idempotency flow. Because
  GraphQL consumes that same registry, the resource also appears automatically
  as the `Contact` type at `/graphql`, guarded by `contacts:read`.

## A SQL-injection risk caught before shipping

`ExportProviderInterface::iterate()` accepts a caller-selected `$fields`
list — meant for sparse fieldsets (spec section 20.7), which an API layer
will eventually pass straight from a request. `ContactExportProvider` and
`CompanyExportProvider` whitelist `$fields` against their own known column
list before interpolating it into SQL; `ContactExportProviderTest` includes
a regression test that literally attempts `'DROP TABLE kontor_contacts; --'`
as a requested field and asserts the table survives.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages. Given how much of this component's actual logic (validation,
duplicate detection, import/export, tagging) only runs against a real
database, most of its meaningful test coverage is in that DB-gated
integration suite rather than the unit suite — worth actually running
against MySQL before relying on this component, not just trusting that it
lints.

## Not in scope for this substage

No API resources for companies, addresses, or memberships yet, and no
CRM-specific concepts (leads, deals, pipelines — that's `kontor/crm`,
Substage 3.3). "Merge" has a permission (`kontor-contacts-merge`) declared
but no implementation yet.
