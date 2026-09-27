# Kontor CRM

`kontor/crm` — leads, pipelines, stages, deals, lead-to-deal conversion,
Kanban board data, and pipeline reports. This is the one component the
specification gives a full worked example for (kontor.md#22.1's canonical
`kontor.json`) — this package follows that example as closely as
Substage 3.3's milestones allow: same package/namespace, same
`CRMServiceInterface` capability name, same provider class names, same
published events (plus one it doesn't list — see below).

Third business component, depending on `kontor/contacts` (lead/deal
`contact_uid`/`company_uid` reference Contacts' records) and `kontor/search`
(same as Contacts and Catalog).

## What kontor.md#22.1 doesn't show, and how the gaps were filled

The canonical manifest example *names* `Kontor\CRM\Contracts\CRMServiceInterface`
as the "crm" capability's contract but never shows its methods — like
`JobInterface` and `CacheInterface` before it, this is the component's own
design: `convertLead()`, `moveDealToStage()`, `closeDealWon()`,
`closeDealLost()`. Other components consume CRM lifecycle operations through
this facade. The shared admin now also carries won-deal customer, title,
value, currency, and stable UID into a prefilled Sales quotation draft.

## Contents

- `migrations/` — `kontor_crm_leads`, `kontor_crm_pipelines`,
  `kontor_crm_stages`, `kontor_crm_deals` (kontor.md#13.1–13.4). None of
  the four have a `deleted_at` column, only `archived_at` — followed
  exactly.
- `src/Application/LeadConversionService.php` — the "conversion" milestone
  (kontor.md diagram 17.1: "Convert to contact/company -> Create deal").
  A lead's schema has no name/email columns of its own, only
  `contact_uid`/`company_uid` — so "qualified" means *already linked* to
  one, and conversion creates a deal from the lead, it doesn't fabricate a
  new contact.
- `src/Application/KanbanBoardService.php` — the "Kanban" milestone: stages
  in column order, each with its deals. The actual drag-and-drop board is
  a frontend concern out of scope here (no admin UI has been built for any
  component yet); this is the data shape a board would render.
- `src/Infrastructure/Reports/PipelineReportProvider.php` — the "reports"
  milestone and the first real consumer of `ReportProviderInterface`
  (kontor.md#9.9, alive in the SDK since Substage 0.2 but never
  implemented until now): deal count and value grouped by stage.
- `src/Application/CRMService.php` — implements `CRMServiceInterface`;
  publishes `crm.deal.stage_changed`/`crm.deal.won`/`crm.deal.lost` (all
  named in the canonical example) plus `crm.lead.converted` (not in that
  example's list, but a clearly meaningful transition the illustrative
  list just didn't happen to include).
- Won deals with a linked contact or company expose a Sales handoff in the
  shared admin. Quotations retain `deal_uid`, so the deal lists every active
  commercial draft or issued document created from it.

## Filled a second Core gap

`ReportProviderInterface` (kontor.md#9.9) has existed in the SDK since
Substage 0.2, but nothing ever built a registry for it — unlike
`ImportProviderRegistry`/`ExportProviderRegistry`/`SearchProviderRegistry`.
Added `Kontor\Core\Infrastructure\Registry\ReportProviderRegistry` to
`kontor/core` itself, mirroring those three exactly.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages.

## Not in scope for this substage

`Kontor\CRM\Admin\LeadsController`/`DealsController` remain declarative
component routes in `kontor.json`; the actual UI is owned by the shared
`ProcessKontor` application. No duplicate detection for leads/deals (that
milestone was Contacts', not CRM's).
