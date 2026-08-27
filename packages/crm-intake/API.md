# Kontor CRM Intake API

`CRMIntakeService::configureDefault()` validates and activates a tenant profile. `fieldsFor()` and `valuesFor()` provide presentation data, `validateAnswers()` and `saveAnswers()` enforce the live profile, and `copyAnswers()` transfers compatible qualification context between records.

Profiles are exported through `KontorSettings` when available. Response data is deliberately excluded from settings migration.
