# Kontor Settings API

## SettingsMigrationService

`Kontor\Settings\Application\SettingsMigrationService` provides:

- `exportProfile(array $source = []): array`
- `decode(string $json): array`
- `preview(array $profile): SettingsMigrationReport`
- `apply(array $profile): SettingsMigrationReport`
- `fingerprint(array $profile): string`

The profile schema is `https://kontor.dev/schema/settings-profile.v1.json`. Imports larger than 1 MB are rejected.

## Adding a provider

Implement `Kontor\Settings\Contracts\SettingsProviderInterface`, then register the instance with `KontorSettings::providerRegistry()->register($provider)`. Provider keys are stable lowercase identifiers. `preview()` must not mutate state; `apply()` must validate again and apply idempotently.
