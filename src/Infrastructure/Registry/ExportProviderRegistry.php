<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\SDK\Contracts\ExportProviderInterface;
use RuntimeException;

/**
 * Components register their ExportProviderInterface here, keyed by the
 * entity type they export (kontor.md#9.7, Substage 1.5 "provider registry"
 * milestone).
 */
final class ExportProviderRegistry
{
    /**
     * @var array<string, ExportProviderInterface>
     */
    private array $providers = [];

    public function register(string $entityType, ExportProviderInterface $provider): void
    {
        $this->providers[$entityType] = $provider;
    }

    public function has(string $entityType): bool
    {
        return isset($this->providers[$entityType]);
    }

    public function get(string $entityType): ExportProviderInterface
    {
        return $this->providers[$entityType]
            ?? throw new RuntimeException("No export provider is registered for entity type \"{$entityType}\".");
    }

    /**
     * @return array<string, ExportProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
