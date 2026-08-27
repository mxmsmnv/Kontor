<?php

declare(strict_types=1);

namespace Kontor\Settings\DTO;

final readonly class SettingsMigrationReport
{
    /**
     * @param ProviderMigrationResult[] $providers
     * @param string[] $warnings
     * @param string[] $errors
     */
    public function __construct(
        public string $fingerprint,
        public bool $applied,
        public array $providers,
        public array $warnings = [],
        public array $errors = [],
    ) {
    }

    public function successful(): bool
    {
        if ($this->errors !== []) {
            return false;
        }

        foreach ($this->providers as $provider) {
            if (!$provider->successful()) {
                return false;
            }
        }

        return true;
    }

    public function changeCount(): int
    {
        return array_sum(array_map(
            static fn (ProviderMigrationResult $result): int => count($result->changes),
            $this->providers,
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'applied' => $this->applied,
            'successful' => $this->successful(),
            'changeCount' => $this->changeCount(),
            'providers' => array_map(
                static fn (ProviderMigrationResult $provider): array => $provider->toArray(),
                $this->providers,
            ),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
