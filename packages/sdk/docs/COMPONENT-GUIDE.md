# Kontor Component Guide

The "documentation" milestone of kontor.md Substage 7.4. This is the
practical companion to
[`../../docs/KONTOR-SPECIFICATION.md`](../../../docs/KONTOR-SPECIFICATION.md) —
read that document for the *why*; this one is the *how*, written for
whoever builds the next component (by hand or via `bin/kontor-make`).

## 1. Directory structure

Every component follows kontor.md section 7's canonical tree:

```text
KontorWidgets/
├── KontorWidgets.module.php
├── kontor.json
├── composer.json
├── LICENSE
├── README.md
├── CHANGELOG.md
├── src/
│   ├── Admin/            Admin controllers/UI glue (none of these
│   ├── Application/      exist yet in this monorepo — no admin UI has
│   ├── Domain/           been built anywhere so far).
│   ├── Infrastructure/
│   ├── Contracts/        Local (non-SDK) interfaces this component
│   ├── DTO/               defines for its own extension points — see
│   ├── Events/             "Registry pattern" below.
│   ├── Listeners/
│   ├── Services/
│   ├── Repositories/
│   ├── Validation/
│   ├── Policies/
│   ├── Providers/
│   └── Support/
├── migrations/
├── resources/
│   ├── views/
│   ├── assets/
│   ├── translations/{en,fr,de,es}/messages.json
│   ├── templates/
│   └── demo/
├── tests/
│   ├── Unit/          no database — real logic, must actually run
│   ├── Integration/   needs KONTOR_TEST_DB_DSN — see section 4
│   ├── Migration/
│   └── E2E/
└── docs/
```

Every hand-built package in this monorepo only populates the subset of
`src/` it actually needs (`Application`, `Domain`, `Infrastructure` cover
almost everything so far) — `ComponentScaffolder` still generates the
full tree with `.gitkeep` placeholders in the unused directories, because
a scaffolder's job is to reproduce the spec faithfully, not the
shortcuts taken under it. Delete what you don't end up using.

## 2. Standard columns (kontor.md #10.3/#10.4)

Every real, standalone table has:

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | internal id, never exposed |
| `uid` | `CHAR(26) UNIQUE` | ULID (`Kontor\SDK\ValueObjects\Uid`), the public identifier |
| `organization_id` | `BIGINT UNSIGNED NOT NULL` | internal numeric id — see section 3 |
| `created_at` / `updated_at` | `DATETIME(6)` | microsecond precision |
| `version` | `INT UNSIGNED DEFAULT 1` | bumped on every update |
| `archived_at` | `DATETIME(6) NULL` | soft delete — reversible, never a hard `DELETE` |

A few spec-defined tables (e.g. `kontor_extensions`) deliberately have
fewer columns — follow the spec's own schema for those exactly rather
than "improving" it.

## 3. The org uid-vs-internal-id pattern

Domain entities and DTOs carry `organizationId` as the public `uid`
string. The `organization_id` *column* is always the internal `BIGINT`.
Repositories resolve one to the other via
`Kontor\Core\Infrastructure\Persistence\OrganizationRepository::internalIdOf(string $uid): int`
before querying, and the reverse via a small `organizationUidFor(int $id): string`
lookup when hydrating rows back into entities. Never store or compare
the public uid directly against `organization_id`.

## 4. Money

Always `Kontor\SDK\ValueObjects\Money` (minor units + `currency_code
CHAR(3)`), never a float column or a float property. Reports that
aggregate money (`ReportSchema`) type those fields `'money'`, not
`'decimal'`.

## 5. The `RepositoryInterface` shape

```php
interface RepositoryInterface
{
    public function find(string $id): ?object;
    public function require(string $id): object;
    public function save(object $entity): void;
    public function archive(string $id): void;
    public function restore(string $id): void;
}
```

Concrete repositories narrow the return types (covariance) — e.g.
`find(string $id): ?Widget` — and add their own component-specific query
methods (`forOrganization()`, `findByKey()`, etc.) beyond the interface.
`save()` always does an upsert (`INSERT ... ON DUPLICATE KEY UPDATE`)
rather than separate insert/update methods.

## 6. The "gap-fill" pattern

kontor.md doesn't specify every table or interface a component needs —
sections 11-16 stop well short of covering every later component (e.g.
there is no dedicated schema section for Custom Entities or Automation).
When the spec is silent, design the missing piece when its first real
consumer needs it, follow the conventions in this guide, and document
*why* in a comment at the point of the gap (see
`Kontor\Entities\Migrations\Migration0001CreateDefinitionsTable` for an
example). Don't invent speculative schema for a consumer that doesn't
exist yet.

## 7. The "registry" extension-point pattern

When a component needs to be pluggable (e.g. Dashboard's widgets,
Automation's action handlers, Core's report providers), the shape is
always:

1. A local `Contracts\*Interface` the plugin implements.
2. A `*Registry` with `register()`/`has()`/`get()` (the last one throws
   if missing) /`all()`.
3. Exactly one trivial built-in implementation that proves the pipeline
   end-to-end (e.g. `WelcomeWidgetProvider`, `LogActionHandler`).

Registries are never retrofitted into already-shipped components after
the fact — built once, adopted by whoever needs it next.

## 8. Testing conventions

- `tests/Unit/` needs no database and must actually execute (not skip).
  Where a service mixes pure logic with DB access, split it: pure
  methods that take/return plain arrays or domain objects, and a thin
  DB-touching wrapper on top (e.g. `EntityViewService::filterRecords()`
  vs. `apply()`). This is what makes the pure half unit-testable at all.
- `tests/Integration/` needs a real MySQL/MariaDB instance. Gate every
  such test behind `KONTOR_TEST_DB_DSN` and extend the shared
  `Kontor\Core\Testing\DatabaseTestCase` (added in this same substage)
  instead of hand-rolling the connect/migrate/seed/cleanup boilerplate:

  ```php
  final class WidgetRepositoryTest extends DatabaseTestCase
  {
      protected function migrations(): array
      {
          return [
              new Migration0001CreateOrganizationsTable(),
              new Migration0001CreateWidgetsTable(),
          ];
      }

      protected function tablesToDrop(): array
      {
          return ['kontor_widgets', 'kontor_organizations', 'kontor_migrations'];
      }

      public function test_save_and_find(): void
      {
          $repository = new WidgetRepository($this->pdo, new OrganizationRepository($this->pdo));
          // $this->organizationUid is already seeded by the base class.
      }
  }
  ```

  `DatabaseTestCase` lives in `kontor/core`, not `kontor/sdk`, because it
  depends on `MigrationRunner`/`OrganizationRepository`, which themselves
  depend on `kontor/sdk` — putting it in the SDK would invert that
  dependency.
- A constructor that needs a live `\PDO` but is never actually queried in
  a given unit test can be satisfied with a `FakePdo extends \PDO {
  public function __construct() {} }` to skip the real connection.

## 9. Scaffolding with `bin/kontor-make`

```bash
# Full canonical component skeleton
bin/kontor-make make:component --name=Widgets --slug=widgets \
    --title="Kontor Widgets" --description="..." --target=packages/widgets

# A Domain entity + matching Repository pair
bin/kontor-make make:entity --namespace='Kontor\Widgets' --entity=Widget \
    --table=kontor_widgets --target=packages/widgets

# A numbered migration skeleton
bin/kontor-make make:migration --namespace='Kontor\Widgets\Migrations' \
    --component=widgets --number=1 --name=create_widgets_table \
    --table=kontor_widgets --target=packages/widgets/migrations

# A ReportProviderInterface skeleton
bin/kontor-make make:report --namespace='Kontor\Widgets\Infrastructure\Reports' \
    --class=WidgetSummaryReportProvider --key=widgets_summary \
    --title="Widget Summary" --target=packages/widgets/src/Infrastructure/Reports
```

Every command supports:

- `--dry-run` — print what would be written without touching disk;
- `--non-interactive` — fail immediately (exit code 1) listing missing
  required options, instead of prompting for them on STDIN.

JSON output, an audit trail, and confirmation flags (kontor.md section
35's full cross-cutting CLI requirements) are intentionally out of scope
here: scaffolding only ever creates new files, it never touches existing
data, so the risk profile that those three features exist to manage
doesn't apply the way it would to `restore:*` or `migration:*`. They
remain open work for whichever future substage builds out the rest of
the CLI command families.

Every scaffolder class (`Kontor\SDK\Scaffolding\ComponentScaffolder`,
`EntityScaffolder`, `MigrationScaffolder`, `ReportScaffolder`) also has a
plain `generate(string $targetDir, bool $dryRun = false): array`
method returning `[relativePath => contents]`, so they can be driven
directly from PHP (a future admin UI, another CLI, a test) without
going through `bin/kontor-make` at all.
