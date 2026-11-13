# Kontor AI integration map

Kontor AI uses the default provider configured in Squad. The workbench offers summary, extraction and drafts. Excel contact import offers optional suggestions for unknown headers. Active CRM deals and tasks offer on-demand next steps. The AI assistant on supported admin screens uses a workflow-specific prompt and only the notes staff explicitly enter. It produces suggestions for review. The table describes use cases and further record-aware integrations.

Built-in actions also cover mail replies, document sections, report totals, project status, quotation emails, invoice reminders, stock balances, purchase orders, expenses, payment reconciliation, catalog descriptions, qualification profiles, recent discussions, dashboard metrics, file metadata and search terms. Availability depends on the record, installed components and staff permissions. The forms show what will be sent and return drafts for review.

| Component | Suggested use |
| --- | --- |
| Core | Explain import errors and summarize administrator diagnostics |
| AI | Test the provider and review drafts |
| API | Explain sanitized responses and OpenAPI examples in the admin area |
| Automation | Draft disabled rules and explain dry runs |
| Cache | Explain aggregate hit rates |
| Catalog | Draft descriptions and suggest categories |
| Collaboration | Summarize threads the current user can read |
| Contacts | Suggest import mapping from header labels without sending rows |
| CRM | Summarize deal history and suggest next steps |
| CRM Intake | Suggest questions or candidate answers against the current schema |
| Dashboard | Explain metrics already filtered for the current user |
| Demo | Explain the workflow using fixture data |
| Documents | Draft wording or suggest document fields |
| Entities | Draft a custom entity schema for validation |
| Expenses | Suggest receipt fields and categories |
| Files | Suggest tags or summarize an explicitly selected authorized file |
| Germany | Draft localized wording |
| GraphQL | Explain the allowed schema in the admin area |
| Inventory | Explain stock trends and flag possible shortages |
| Invoices | Draft payment reminders and explain aging |
| Ledger | Explain discrepancies and suggest candidate accounts |
| Mail | Summarize threads and draft replies |
| Marketplace | Summarize published compatibility notes and advisories |
| MCP | Expose existing scoped tools for explicit client use |
| Payments | Suggest reconciliation matches from limited metadata |
| Portal | Draft help from public material or current customer context |
| Projects | Summarize milestones and draft plans |
| Purchasing | Explain variances and draft purchase order notes |
| Queue | Summarize redacted failures |
| Reports | Explain scoped aggregates with links to source reports |
| Sales | Draft quotations and follow-up wording |
| SDK | Provide typed contracts and examples |
| Search | Suggest query rewrites using current access filters |
| Settings | Explain configuration changes with secrets excluded |
| Tasks | Draft descriptions and checklists from visible tasks |
| Workflow | Draft states and transitions for review |

Each proposed use should preserve the component's permissions and validation. The model supplies suggestions and drafts. Existing services own calculations, permissions, payments, stock movements, sending, publishing and other writes.
