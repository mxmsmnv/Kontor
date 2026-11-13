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
- `kontor_crm_record_archive_action` applies a reviewed archive or restore to a lead or deal under its Workspace permission.
- `kontor_crm_lead_create`, `kontor_crm_lead_update`, `kontor_crm_deal_create` and `kontor_crm_deal_update` accept the documented JSON fields, a per-user idempotency key and an optional dry-run preview. `kontor_crm_intake_get` describes custom lead and deal fields. Create deals in an open stage and use the transition tool for later stage changes.

## Task tools

When Kontor Tasks is installed, the gateway advertises these additional tools.

| Tool | Scope | Operation |
| --- | --- | --- |
| `kontor_tasks_list` | `read` | Find visible tasks by title or description, status, priority, and archive state. Use `nextOffset` to continue. |
| `kontor_task_get` | `read` | Read a visible task by UID. |
| `kontor_task_assignment_options` | `read` | Find eligible staff and department audiences for a linked user who can assign and create or edit tasks. |
| `kontor_task_create` | `draft` | Create a task and optionally link a visible CRM contact or company. |
| `kontor_task_update` | `draft` | Patch the supplied fields on a visible, active task. |
| `kontor_task_action` | `draft` | Start, complete or reopen a visible task. Completion can create the next recurrence. |
| `kontor_task_reviewed_action` | `draft` | Cancel, archive or restore a visible task. Confirm with `APPLY_REVIEWED_KONTOR_TASK_ACTION`. |

Task access always uses the linked user's Workspace permissions and assignee, department and organization scope. The task's default audience is the linked user. Assigning another person or changing audience requires the Workspace assignment permission. Linking a task to a customer requires relation management permission and permission to view the target contact or company.

Create and update use a closed `fields` object containing `title`, `description`, `priority`, `dueAt`, `recurrenceRule`, `recurrenceUntil`, `assignedTo`, `visibility` and `departmentRoleId`. Dates use ISO 8601 with an explicit offset, except `recurrenceUntil`, which uses YYYY-MM-DD. Use null to clear an optional field and omit a field to preserve it on update. Optional `related_customer` on create takes `type` (`contact` or `company`) and `uid`.

Writes take a per-user `idempotency_key` and return the task UID while it remains visible to that user. Completion can also return the next visible task UID. Mutation receipts commit alongside the task changes and account-attributed audit entries.
