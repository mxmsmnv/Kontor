# Changelog

All notable changes to Kontor are documented here.

## Unreleased

### Added

- Add a Task access screen to assign staff access levels and manage department membership.

- Scope tasks to an assignee, a department, or the organization, with separate manager visibility.

- Create a selectable employee role with CRM and Contacts view/edit permissions and optional Workspace access.

- Preview and install the connected demo's required modules in dependency order with the demo setup command.

### Fixed

- Install Search before Contacts when ProcessWire resolves CRM dependencies in one step.

- Align personal dashboard cards with the grid and make Earlier and Later change the displayed widget order.
- Allow closed tasks to be reopened without generating duplicate recurring tasks.
- Preserve native button text colors in Konkat and restore missing surface and border styles in Core Original.
- Explain when the demo creates records and distinguish missing optional modules from missing demo dependencies.

## [1.1.1] - 2026-10-10

### Fixed

- Declare the ProcessWire namespace in every component discovery wrapper so ProcessWire resolves the loaded component class as a valid `ProcessWire\Module` during installation.

## [1.1.0] - 2026-10-09

### Added

- Root-level, cross-platform discovery wrappers for all 34 optional ProcessWire component modules.
- Shared document sections, template-derived letter and contract forms, optional company-bound PDFs, and company file listings.

### Fixed

- Resolve admin templates from the module root after the renderer moved into a trait.

## [1.0.0] - 2026-09-27

### Added

- First public release of the Kontor ERP, CRM and business-operations suite for ProcessWire.
- Thirty-five installable components covering customer operations, commerce, finance, collaboration, platform services and integrations.
- Permission-aware ProcessWire admin, REST, GraphQL and scoped MCP integration surfaces.
- Migration, audit, backup, import/export, health-check and deterministic test infrastructure.
