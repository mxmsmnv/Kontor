<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\SDK\Contracts\RepositoryInterface;
use RuntimeException;

/**
 * Maps an entity type to its RepositoryInterface (kontor.md#9.4). Used by
 * ImportManager::rollback() to archive every record a batch created or
 * updated, without ImportManager needing to know which component owns
 * that entity type.
 */
final class RepositoryRegistry
{
    /**
     * @var array<string, RepositoryInterface>
     */
    private array $repositories = [];

    public function register(string $entityType, RepositoryInterface $repository): void
    {
        $this->repositories[$entityType] = $repository;
    }

    public function has(string $entityType): bool
    {
        return isset($this->repositories[$entityType]);
    }

    public function get(string $entityType): RepositoryInterface
    {
        return $this->repositories[$entityType]
            ?? throw new RuntimeException("No repository is registered for entity type \"{$entityType}\".");
    }
}
