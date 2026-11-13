# Contact intake fields over MCP

When Kontor Contacts and CRM Intake are installed, `kontor_contact_fields_get`
lists the contact intake form defined in Workspace. Pass the contact UID to
include the saved answers.

Use `kontor_contact_fields_update` with the contact UID and a JSON object
containing the configured keys you want to fill. Set `preview: true` to validate
the merged answers without writing. Omit a key to keep its existing value or
pass null to clear an optional answer. This follows the active form's required
fields and selectable options.

The linked Kontor account needs contact view permission. Updating answers
also requires contact edit permission and an MCP client with Draft access.
The base contact record is created or edited through the record tools.
