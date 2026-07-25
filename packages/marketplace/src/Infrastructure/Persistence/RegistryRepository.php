<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Infrastructure\Persistence;

use Kontor\Marketplace\Domain\Registry;
use RuntimeException;

/**
 * Instance-wide, not tenant-scoped — same reasoning
 * `Kontor\Core\Infrastructure\Registry\ComponentRegistry` already
 * follows for `kontor_components`, addressed by `name` rather than a
 * `uid`.
 */
final class RegistryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function find(string $name): ?Registry
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_marketplace_registries WHERE name = :name');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $name): Registry
    {
        return $this->find($name) ?? throw new RuntimeException("Registry \"{$name}\" was not found.");
    }

    public function save(Registry $registry): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_marketplace_registries
                (name, url, type, trusted, status, last_synced_at, created_at, updated_at)
             VALUES
                (:name, :url, :type, :trusted, :status, :last_synced_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                url = VALUES(url), status = VALUES(status), last_synced_at = VALUES(last_synced_at),
                updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'name' => $registry->name,
            'url' => $registry->url,
            'type' => $registry->type,
            'trusted' => $registry->trusted ? 1 : 0,
            'status' => $registry->status,
            'last_synced_at' => $registry->lastSyncedAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $registry->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $registry->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return Registry[]
     */
    public function active(): array
    {
        $statement = $this->pdo->query("SELECT * FROM kontor_marketplace_registries WHERE status = 'active' ORDER BY name");

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Registry[]
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_marketplace_registries ORDER BY name');

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Registry
    {
        return new Registry(
            name: $row['name'],
            url: $row['url'],
            type: $row['type'],
            trusted: (bool) $row['trusted'],
            status: $row['status'],
            lastSyncedAt: $row['last_synced_at'] !== null ? new \DateTimeImmutable($row['last_synced_at']) : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
