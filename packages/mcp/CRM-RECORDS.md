# CRM record tools

When Kontor CRM is installed, Kontor MCP exposes four `draft` tools for lead
and deal creation and editing. The linked user's Workspace permissions apply
to every call. Records and pipeline stages belong to the Workspace
organization. Linking a contact or company additionally requires the
corresponding customer view permission.

| Tool suffix | Required fields for creation | Permission |
| --- | --- | --- |
| `kontor_crm_lead_create` | `title` | Lead create |
| `kontor_crm_lead_update` | Record `uid` | Lead edit |
| `kontor_crm_deal_create` | `title`, `pipeline_uid`, `stage_uid` | Deal create |
| `kontor_crm_deal_update` | Record `uid` | Deal view and edit |

All four accept a `fields` JSON object encoded as a string, an
`idempotency_key`, and optional `preview`. The linked user and action are
recorded in the CRM audit log after saving. Preview returns the proposed
fields without saving. Use the same key and same fields for a retry. A retry
with different fields and the same key is rejected.

Common fields are `title`, `contact_uid`, `company_uid`, `source`,
`description`, `currency`, and `intake_answers`. Use a three-letter uppercase
currency with amounts in integer minor units. Omit fields to retain existing
values. Send `null` to clear optional fields.

- Leads also accept `priority`, `estimated_value_minor`, and
  `next_action_at` (`YYYY-MM-DDTHH:MM`). They start as `new` and can be edited
  to `new`, `contacted`, `qualified`, or `lost`. Use
  `kontor_crm_lead_convert` to convert qualified leads.
- Deals also accept `value_minor`, `probability` (integer 0–100), and
  `expected_close_date` (`YYYY-MM-DD`). New deals require a stage of type
  `open` in the selected pipeline. Use `kontor_crm_deal_transition` for
  stage moves and closing.

When Kontor CRM Intake is installed, `kontor_crm_intake_get` lists configured
fields for `entity: "lead"` or `entity: "deal"`. Include a record UID to see
saved answers if your Workspace role permits opening that record. Send changed
answers under `intake_answers` using the field keys returned by discovery.
Existing answers are merged with submitted values and the configured required
fields are validated. A populated intake field bound to source supplies
the CRM source value.

Example `fields` JSON document for a lead:

```json
{
  "title": "Spring showroom inquiry",
  "estimated_value_minor": 450000,
  "currency": "USD",
  "next_action_at": "2026-11-20T10:30"
}
```

Add any intake fields required by the active profile before saving. Use the
lead, deal, pipeline and stage lookup tools to find record UIDs and reread
saved records.
