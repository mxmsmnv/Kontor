# Changelog

All notable changes to `kontor/dashboard` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Widget rendering now consumes Kontor Cache with organization/user/layout
  scoped keys, configurable TTLs, cache hit visibility, and tag invalidation
  after layout mutations.

- First admin vertical: create a default personal dashboard, add/remove
  registered widgets, persist width and horizontal position, and render the
  saved layout on the existing Kontor home dashboard.
- Initial alpha (Substage 5.3): `kontor_dashboards` and
  `kontor_dashboard_widgets` migrations (schema gap-fill);
  `WidgetProviderInterface` (own extension point, not an SDK contract) and
  `WidgetRegistry` (register/has/get-throws/all, mirroring
  `ReportProviderRegistry`'s shape); `Dashboard`/`DashboardWidget` domain
  objects; `DashboardRepository` (implementing `RepositoryInterface`, plus
  `forOwner()`/`forRole()`/`defaultForOwner()`/`defaultForRole()`) and
  `DashboardWidgetRepository`; `DashboardService`
  (`createPersonalDashboard()`/`createRoleDashboard()` with only-one-
  default-per-scope enforcement, `addWidget()` validated against the
  registry, `moveWidget()`/`resizeWidget()`, `dashboardFor()` — personal
  default falling back to role default, `render()` — layout + each
  widget's rendered data); `WelcomeWidgetProvider`, a trivial built-in
  widget proving the pipeline end-to-end; `DashboardHealthCheck`;
  permissions; en/fr/de/es translations. Third component of Stage 5.

### Fixed

- Widget cache scopes are hashed into WireCache-safe names so long
  organization, layout, and tag identities cannot be truncated in storage.
- Widget placement and rendering now reject cross-organization dashboards.
