# Kontor CRM Intake

Optional, tenant-scoped qualification forms for `contact`, `lead`, and `deal` records. Field definitions live in a portable profile; captured answers remain business data and are never included in settings exports.

Supported field types are text, textarea, URL, select, multiselect, and datetime. Every field has a label, description, note, target records, required flag, group, and ordered option map. The UI renders only the active organization's default profile.

When a lead is converted, answers whose definitions also target deals are copied to the new deal. Removing the module preserves profiles and answers.
