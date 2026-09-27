<?php

declare(strict_types=1);

namespace Kontor\Settings\Application;

use InvalidArgumentException;
use JsonException;
use Kontor\Settings\Contracts\SettingsMigrationInterface;
use Kontor\Settings\DTO\SettingsMigrationReport;

final class SettingsMigrationService implements SettingsMigrationInterface
{
    public const SCHEMA = 'https://kontor.dev/schema/settings-profile.v1.json';
    public const SCHEMA_VERSION = 1;
    public const MAX_JSON_BYTES = 1_048_576;

    public function __construct(private readonly SettingsProviderRegistry $providers)
    {
    }

    /** @param array<string, mixed> $source */
    public function exportProfile(array $source = []): array
    {
        $settings = [];

        foreach ($this->providers->all() as $key => $provider) {
            $data = $provider->export();
            $this->assertNoSensitiveKeys($data, "providers.{$key}.data");
            $settings[$key] = [
                'schemaVersion' => 1,
                'data' => $data,
            ];
        }

        return [
            '$schema' => self::SCHEMA,
            'schemaVersion' => self::SCHEMA_VERSION,
            'product' => 'Kontor',
            'exportedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'source' => $source,
            'providers' => $settings,
        ];
    }

    /** @return array<string, mixed> */
    public function decode(string $json): array
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Choose a non-empty Kontor settings file smaller than 1 MB.');
        }

        try {
            $profile = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The selected file is not valid JSON.', previous: $exception);
        }

        if (!is_array($profile)) {
            throw new InvalidArgumentException('The settings profile must contain a JSON object.');
        }

        return $profile;
    }

    /** @param array<string, mixed> $profile */
    public function preview(array $profile): SettingsMigrationReport
    {
        return $this->process($profile, false);
    }

    /** @param array<string, mixed> $profile */
    public function apply(array $profile): SettingsMigrationReport
    {
        $preview = $this->preview($profile);

        if (!$preview->successful()) {
            throw new InvalidArgumentException('Settings cannot be imported until every validation error is resolved.');
        }

        return $this->process($profile, true);
    }

    /** @param array<string, mixed> $profile */
    public function fingerprint(array $profile): string
    {
        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $profile */
    private function process(array $profile, bool $apply): SettingsMigrationReport
    {
        $errors = [];
        $warnings = [];

        if (($profile['$schema'] ?? null) !== self::SCHEMA) {
            $errors[] = 'This is not a supported Kontor settings profile.';
        }
        if (($profile['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'This settings profile uses an unsupported schema version.';
        }
        if (($profile['product'] ?? null) !== 'Kontor') {
            $errors[] = 'The profile was not created by Kontor.';
        }

        $payloads = $profile['providers'] ?? null;
        if (!is_array($payloads) || $payloads === []) {
            $errors[] = 'The settings profile does not contain any providers.';
            $payloads = [];
        }

        try {
            $this->assertNoSensitiveKeys($payloads, 'providers');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        $results = [];
        if ($errors === []) {
            foreach ($payloads as $key => $payload) {
                if (!is_string($key) || !is_array($payload)) {
                    $errors[] = 'Every settings provider entry must be a named JSON object.';
                    continue;
                }
                if (!$this->providers->has($key)) {
                    $warnings[] = "Provider \"{$key}\" is not available on this site and will be skipped.";
                    continue;
                }
                if (($payload['schemaVersion'] ?? null) !== 1 || !is_array($payload['data'] ?? null)) {
                    $errors[] = "Provider \"{$key}\" has an unsupported payload.";
                    continue;
                }

                $provider = $this->providers->get($key);
                $results[] = $apply
                    ? $provider->apply($payload['data'])
                    : $provider->preview($payload['data']);
            }
        }

        return new SettingsMigrationReport(
            fingerprint: $this->fingerprint($profile),
            applied: $apply,
            providers: $results,
            warnings: $warnings,
            errors: $errors,
        );
    }

    /** @param array<string, mixed> $data */
    private function assertNoSensitiveKeys(array $data, string $path): void
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $currentPath = $path . '.' . $key;

            if (preg_match('/(?:pass(?:word)?|secret|token|api[_-]?key|private[_-]?key|credential|auth[_-]?salt)/i', $key)) {
                throw new InvalidArgumentException(
                    "Sensitive setting \"{$currentPath}\" is not allowed in a migration profile."
                );
            }

            if (is_array($value)) {
                $this->assertNoSensitiveKeys($value, $currentPath);
            }
        }
    }
}
