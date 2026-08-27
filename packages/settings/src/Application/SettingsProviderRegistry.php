<?php

declare(strict_types=1);

namespace Kontor\Settings\Application;

use Kontor\Settings\Contracts\SettingsProviderInterface;
use RuntimeException;

final class SettingsProviderRegistry
{
    /** @var array<string, SettingsProviderInterface> */
    private array $providers = [];

    public function register(SettingsProviderInterface $provider): void
    {
        $key = $provider->key();

        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $key) !== 1) {
            throw new RuntimeException("Settings provider key \"{$key}\" is invalid.");
        }

        if (isset($this->providers[$key])) {
            throw new RuntimeException("Settings provider \"{$key}\" is already registered.");
        }

        $this->providers[$key] = $provider;
        ksort($this->providers);
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): SettingsProviderInterface
    {
        return $this->providers[$key]
            ?? throw new RuntimeException("Settings provider \"{$key}\" is not registered.");
    }

    /** @return array<string, SettingsProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }
}
