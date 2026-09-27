<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\SDK\Contracts\BackupProviderInterface;
use RuntimeException;

/**
 * Components register their BackupProviderInterface here (kontor.md#9.5,
 * Substage 1.4 "backup provider registry" milestone) so BackupManager can
 * back up and restore any component by name without knowing its concrete
 * implementation.
 */
final class BackupProviderRegistry
{
    /**
     * @var array<string, BackupProviderInterface>
     */
    private array $providers = [];

    public function register(string $component, BackupProviderInterface $provider): void
    {
        $this->providers[$component] = $provider;
    }

    public function has(string $component): bool
    {
        return isset($this->providers[$component]);
    }

    public function get(string $component): BackupProviderInterface
    {
        return $this->providers[$component]
            ?? throw new RuntimeException("No backup provider is registered for component \"{$component}\".");
    }

    /**
     * @return array<string, BackupProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
