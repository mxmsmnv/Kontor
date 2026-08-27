# Kontor Settings

Kontor Settings moves portable workspace defaults between Kontor installations through a versioned JSON profile.

The first provider covers organization defaults: display and legal names, country, language, currency, timezone and date format. Other installed components can register their own provider through `SettingsProviderRegistry`.

Import is deliberately two-step: Kontor validates and previews every change, then requires explicit confirmation before applying it. Providers that are unavailable on the destination are reported and skipped, so a profile does not require every optional Kontor component to be installed.

Passwords, tokens, API keys, private keys and credential-like values are rejected. Business records such as contacts, deals, invoices and payments are outside the settings profile.

## Admin workflow

1. Open **Kontor → Settings migration**.
2. Download a JSON profile from the source installation.
3. Select it on the destination and preview the proposed changes.
4. Review every changed value and confirm the import.

Permissions are split into `kontor-settings-export` and `kontor-settings-import`.
