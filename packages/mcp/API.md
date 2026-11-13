# Kontor MCP API

`ProcessWire\\KontorMCP` declares `mcpProvider => true` and implements `mcpProviderInfo()` and `mcpTools()` for MCP Server 1.0.

Version 1.0.1 also opts into PocketWire with `pocketWireProviderInfo()`, `pocketWireManifest(User $actor)`, and `pocketWireRead(...)`. That SDK surface is read-only and requires the current actor to hold `kontor-access`. Resource metadata and records are filtered by an explicit resource-to-view-permission allowlist; search requires explicit entity types and checks each type's component view permission. Unknown and dynamic resources fail closed for non-superusers until their permission mapping becomes a documented contract. Reads use the same organization-scoped bridge and live API resource schemas. PocketWire exposes no mutation, validation, settings, secret, file, shell, or external-call action.

The provider publishes 13 tools covering readiness, components, live resource schemas, bounded search, resource list/get/validate/create/update/archive, and settings export/preview/apply.

JSON record attributes, filters, sort instructions, sparse fields and settings profiles are passed as bounded JSON strings. The bridge decodes them only after MCP Server validates the closed outer schema, then validates resource names, capabilities, fields and value types against the live `ApiResourceSchema`.

A component can register an `ApiResourceInterface` with `KontorAPI::resourceRegistry()`. Generic operations remain subject to the linked user's resource permissions. The archive tool requires a resource that implements `ArchivableApiResourceInterface`.

## Optional Workspace tools

- [Contact intake fields](CONTACT-INTAKE.md) can be read, previewed and filled on an existing contact.
- `kontor_projects_list` and `kontor_project_get` expose project lookup when the linked user has project view permission. Project create, partial update, archive and restore use `kontor_project_create`, `kontor_project_update` and `kontor_project_archive_action`. Archive or restore requires `APPLY_PROJECT_ARCHIVE_ACTION`. Project customer selection requires view permission on the referenced contact or company.
- `kontor_project_milestones_list` lists milestones and supplies `nextOffset` when another page is available. `kontor_project_milestone_create` and `kontor_project_milestone_action` add, complete and reopen milestones. `kontor_project_time_log` records manual time for the linked user and `kontor_project_billable_item_create` records a billable item in the project currency. Each write requires Draft scope, the corresponding Workspace permission and an idempotency key.
- `kontor_crm_leads_list`, `kontor_crm_lead_get`, `kontor_crm_deals_list`, `kontor_crm_deal_get`, `kontor_crm_pipelines_list` and `kontor_crm_stages_list` expose scoped CRM lookup. Full lead detail requires lead edit, as in the Workspace form.
- `kontor_crm_lead_convert` and `kontor_crm_deal_transition` use CRM workflow permissions and require an explicit confirmation and an idempotency key.
- `kontor_crm_record_archive_action` applies a reviewed archive or restore to a lead or deal under its Workspace permission.
- [CRM record mutations and intake](CRM-RECORDS.md). `kontor_crm_lead_create`, `kontor_crm_lead_update`, `kontor_crm_deal_create` and `kontor_crm_deal_update` accept the documented JSON fields, a per-user idempotency key and an optional dry-run preview. `kontor_crm_intake_get` describes custom lead and deal fields. Create deals in an open stage and use the transition tool for later stage changes.

- `kontor_companies_list`, `kontor_company_get`, `kontor_company_create`, `kontor_company_update` and `kontor_company_archive_action` expose CRM company records and their configured custom fields under the matching company permission. Company creation and updates require Draft access.

## Inventory and purchasing tools

- `kontor_inventory_warehouses_list` and `kontor_inventory_balances_list` require inventory stock view. `kontor_inventory_movements_list` requires movement view.
- `kontor_purchasing_suppliers_list` requires supplier view. `kontor_purchase_orders_list` and `kontor_purchase_order_get` require purchase-order view. Supplier IDs on orders also require supplier view.
- `kontor_purchase_order_create_draft` requires Draft, purchase-order create, supplier view and an idempotency key. Provide an active supplier, an active warehouse and one to 25 active inventory-tracked catalog items with quantities and prices in minor currency units. The tool stores the order and calculated totals as a draft.

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
| `kontor_task_reminders_list` | `read` | Read reminder times and delivery status for a visible task. |
| `kontor_task_reminder_schedule` | `draft` | Queue a future email reminder for the explicitly reviewed assignee. Confirm with `SCHEDULE_REVIEWED_TASK_EMAIL`. |
| `kontor_task_relations_list` | `read` | List linked CRM records the actor can view. |
| `kontor_task_relation_link` | `draft` | Add an authorized contact or company link to an active task. |
| `kontor_task_relation_unlink` | `draft` | Archive an authorized customer link on an active task. Confirm with `UNLINK_REVIEWED_TASK_RECORD`. |

Task access always uses the linked user's Workspace permissions and assignee, department and organization scope. The task's default audience is the linked user. Assigning another person or changing audience requires the Workspace assignment permission. Linking a task to a customer requires relation management permission and permission to view the target contact or company.

Create and update use a closed `fields` object containing `title`, `description`, `priority`, `dueAt`, `recurrenceRule`, `recurrenceUntil`, `assignedTo`, `visibility` and `departmentRoleId`. Dates use ISO 8601 with an explicit offset, except `recurrenceUntil`, which uses YYYY-MM-DD. Use null to clear an optional field and omit a field to preserve it on update. Optional `related_customer` on create takes `type` (`contact` or `company`) and `uid`.

Writes take a per-user `idempotency_key` and return the task UID while it remains visible to that user. Completion can also return the next visible task UID. Mutation receipts commit alongside the task changes and account-attributed audit entries.

## File tools

`kontor_files_list` searches file names, and `kontor_file_get` reads metadata by UID. Both enforce the linked user's file and record permissions. List results can contain an empty page after record filtering. Follow `next_offset` until null.

`kontor_file_download` returns base64 bytes for active files up to 512 KiB and additionally requires file download permission. The metadata tools omit private storage paths and signed URLs.


Reminder scheduling requires the task reminder management permission. Review the task UID, current assignee and future reminder timestamp before submitting. The request must supply that assignee's ID as `expected_assignee_id`. The server reads the recipient address from the assigned user's account and queues the message through the same notification service as Workspace. The tool response contains the reminder UID.

Managing links on existing tasks requires task edit and relation management permissions plus permission to view the target contact or company. Link and reminder lists return up to 50 items with `nextOffset` when more items exist.


When Kontor Collaboration is installed, task tools additionally include:

| Tool | Scope | Operation |
| --- | --- | --- |
| `kontor_task_discussion_list` | `read` | Read notes or comments with the matching view permission. |
| `kontor_task_discussion_post` | `draft` | Post a note or comment, with an optional same-task comment parent. |
| `kontor_task_discussion_archive` | `draft` | Archive a note or comment with the matching permission. Confirm with `ARCHIVE_REVIEWED_TASK_DISCUSSION`. |
| `kontor_task_follow` | `draft` | Set the linked user's follow preference, which also appears on task get when permitted. |

Posting a comment records mentions and follows the task for its author through the collaboration service. MCP comment posting does not queue email.

### Mail and reports

With the respective components installed, `kontor_mail_list` and `kontor_mail_get` provide read-only active mail lookup with the Workspace mail view permission. Mail linked to tasks is visible only when every linked task passes the linked account's task scope. The list searches subject and sender within the latest 500 matching messages, returning up to 25 visible messages per page. The get tool returns plain text up to 8,000 characters at a time.

`kontor_reports_list` and `kontor_report_run` currently support the CRM pipeline report. The account must hold both report view and CRM deal view permissions. Pass the optional pipeline UID and open, won or lost status to filter. Results contain paged stage summaries and totals for the full filtered report.

### Customer portal and documents

`kontor_portal_accounts_list` and `kontor_portal_account_get` return organization-scoped, non-archived portal account metadata for users with the Workspace account management permission. List accepts an optional contact UID. Responses contain account and contact IDs, email, status and timestamps.

`kontor_document_templates_list` and `kontor_document_template_get` return metadata for organization document templates, including version and archive date, for users with template view permission. List accepts an optional document type. Both lists return up to 25 entries per page. These tools return no credentials, verification material, template source, rendered files or signed download URLs.
