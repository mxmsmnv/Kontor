<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Infrastructure\Registry;

use Kontor\Dashboard\Contracts\WidgetProviderInterface;
use RuntimeException;

/**
 * The "widget registry" milestone. Mirrors Kontor\Core\Infrastructure\
 * Registry\ReportProviderRegistry's register/has/get-throws/all shape.
 * Other components register their own widgets here by depending on
 * kontor/dashboard and calling register() during their own module init —
 * none are built in this substage; see the README.
 */
final class WidgetRegistry
{
    /**
     * @var array<string, WidgetProviderInterface>
     */
    private array $providers = [];

    public function register(WidgetProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): WidgetProviderInterface
    {
        return $this->providers[$key]
            ?? throw new RuntimeException("No widget provider is registered for key \"{$key}\".");
    }

    /**
     * @return array<string, WidgetProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
