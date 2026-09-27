<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\SDK\Contracts\ImportProviderInterface;
use RuntimeException;

/**
 * Components register their ImportProviderInterface here, keyed by the
 * entity type they import (kontor.md#9.6, Substage 1.5 "provider registry"
 * milestone).
 */
final class ImportProviderRegistry
{
    /**
     * @var array<string, ImportProviderInterface>
     */
    private array $providers = [];

    public function register(string $entityType, ImportProviderInterface $provider): void
    {
        $this->providers[$entityType] = $provider;
    }

    public function has(string $entityType): bool
    {
        return isset($this->providers[$entityType]);
    }

    public function get(string $entityType): ImportProviderInterface
    {
        return $this->providers[$entityType]
            ?? throw new RuntimeException("No import provider is registered for entity type \"{$entityType}\".");
    }

    /**
     * @return array<string, ImportProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
