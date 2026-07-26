# Kontor Dashboard

`kontor/dashboard` — widget registry, layouts, personal dashboards, and
role dashboards. Third component of Stage 5. Depends on `kontor/core` and
the shared `kontor/cache` capability for widget render caching.

## Its own schema gap

Sections 11–16 of kontor.md never gave Dashboard a schema section, so both
tables in `migrations/` are this package's own gap-fill, same situation
Tasks and Collaboration were in. kontor.md#29 lists a fuller feature set
("organization dashboards", "drag-and-drop widgets", "configurable
refresh", "cache policies") than Substage 5.3's actual milestones
(`widget registry; layouts; personal dashboards; role dashboards`) — this
package builds exactly the milestones, not the fuller section 29 list; see
"Not in scope" below.

## A widget contract that isn't in the SDK

`WidgetProviderInterface` isn't a canonical contract (kontor.md section 9
has no widget entry) — invented for this substage, same status as
`kontor/search`'s own `SearchIndexerInterface`. It lives local to this
package (`src/Contracts/`) rather than in the SDK, and so does
`WidgetRegistry` — mirroring where `kontor/search`'s own
`SearchProviderRegistry` lives (in `kontor/search` itself, not
`kontor/core`), because this is this package's own extension point, not a
capability every component is assumed to know about. Other components
register their own widgets by depending on `kontor/dashboard` and calling
`KontorDashboard::widgetRegistry()->register()` during their own module
`init()` — none do in this substage; see "Not in scope".

## Contents

- `migrations/` — `kontor_dashboards` (`scope` is `'personal'`, with
  `owner_user_id` set, or `'role'`, with `role` set; "only one default per
  scope" is enforced by `DashboardService`, not a DB constraint — same
  choice `SequenceService` made for its own invariants) and
  `kontor_dashboard_widgets` (the "layouts" milestone: one row per widget
  placed on a dashboard — position, size, per-widget JSON config).
- `src/Widgets/WelcomeWidgetProvider.php` — a trivial, dependency-free
  built-in widget proving the registry/render pipeline end-to-end. Real
  per-component widgets (a Sales revenue widget, a CRM pipeline widget, a
  Tasks "my open tasks" widget, …) are future work for *those* packages to
  build once `kontor/dashboard` exists to depend on, not the other way —
  keeping this package itself dependency-free.
- `src/Application/DashboardService.php` — ties every milestone together:
  `addWidget()` validates its `widgetKey` is actually registered before
  it can be placed on a layout; `dashboardFor()` resolves a user's
  personal default dashboard, falling back to their role's default — the
  actual point of having both a "personal" and a "role" scope;
  `render()` resolves a dashboard's full layout, pairing each
  `DashboardWidget` with the data its registered provider renders.
  Widget payloads are cached per organization, user, layout UID, and config
  for 30 seconds by default (`refreshSeconds` can choose 5–3600 seconds).
  Layout add/move/resize/remove operations invalidate the dashboard tag.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/` (the registry and the built-in widget) needs no database and
runs for real. Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The root `ProcessKontor` home now renders the resolved personal dashboard and
its registered widgets. Users can create a personal default, add/remove the
built-in widget, and persist simple width and horizontal-position changes.

## Not in scope for this substage

Organization-scoped dashboards (kontor.md#29's fuller feature list, not a
Substage 5.3 milestone) — `kontor_dashboards.scope` only supports
`'personal'`/`'role'`. No drag-and-drop; the first admin vertical exposes
explicit layout controls backed by `moveWidget()`/`resizeWidget()`.
The admin does not yet expose a refresh-interval editor; providers can use a
widget's `refreshSeconds` config and the UI shows whether a payload was fresh
or served from Cache. No widgets from other components — see above.
