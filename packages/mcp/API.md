# Kontor MCP API

`ProcessWire\\KontorMCP` declares `mcpProvider => true` and implements `mcpProviderInfo()` and `mcpTools()` for MCP Server 1.0.

Version 1.0.1 also opts into PocketWire with `pocketWireProviderInfo()`, `pocketWireManifest(User $actor)`, and `pocketWireRead(...)`. That SDK surface is read-only and requires the current actor to hold `kontor-access`. Resource metadata and records are filtered by an explicit resource-to-view-permission allowlist; search requires explicit entity types and checks each type's component view permission. Unknown and dynamic resources fail closed for non-superusers until their permission mapping becomes a documented contract. Reads use the same organization-scoped bridge and live API resource schemas. PocketWire exposes no mutation, validation, settings, secret, file, shell, or external-call action.

The provider publishes 13 tools covering readiness, components, live resource schemas, bounded search, resource list/get/validate/create/update/archive, and settings export/preview/apply.

JSON record attributes, filters, sort instructions, sparse fields and settings profiles are passed as bounded JSON strings. The bridge decodes them only after MCP Server validates the closed outer schema, then validates resource names, capabilities, fields and value types against the live `ApiResourceSchema`.

A component can register an `ApiResourceInterface` with `KontorAPI::resourceRegistry()`. Generic operations remain subject to the linked user's resource permissions. The archive tool requires a resource that implements `ArchivableApiResourceInterface`.

## Optional Workspace tools

- [Contact intake fields](CONTACT-INTAKE.md) can be read, previewed and filled on an existing contact.
- `kontor_projects_list` and `kontor_project_get` expose basic project lookup when the linked user has project view permission.
- `kontor_crm_leads_list`, `kontor_crm_lead_get`, `kontor_crm_deals_list`, `kontor_crm_deal_get`, `kontor_crm_pipelines_list` and `kontor_crm_stages_list` expose scoped CRM lookup. Full lead detail requires lead edit, as in the Workspace form.
- `kontor_crm_lead_convert` and `kontor_crm_deal_transition` use CRM workflow permissions and require an explicit confirmation and an idempotency key.
