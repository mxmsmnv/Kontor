<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use RuntimeException;

/**
 * Tracks installed component state in kontor_components (kontor.md#11.2).
 * This is distinct from the CapabilityRegistry: it records install/enable
 * lifecycle, not the capabilities a component provides at runtime.
 */
final class ComponentRegistry implements ComponentRegistryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function markInstalled(string $name, string $version, ?string $source = null, ?string $checksum = null): void
    {
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_components (name, version, status, source, checksum, installed_at, updated_at)
             VALUES (:name, :version, :status, :source, :checksum, :installed_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                version = VALUES(version),
                source = VALUES(source),
                checksum = VALUES(checksum),
                updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'name' => $name,
            'version' => $version,
            'status' => 'installed',
            'source' => $source,
            'checksum' => $checksum,
            'installed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function enable(string $name): void
    {
        $this->requireExists($name);

        $statement = $this->pdo->prepare(
            'UPDATE kontor_components SET status = :status, enabled_at = :now, updated_at = :now WHERE name = :name'
        );
        $statement->execute(['status' => 'enabled', 'now' => $this->now(), 'name' => $name]);
    }

    public function disable(string $name): void
    {
        $this->requireExists($name);

        $statement = $this->pdo->prepare(
            'UPDATE kontor_components SET status = :status, disabled_at = :now, updated_at = :now WHERE name = :name'
        );
        $statement->execute(['status' => 'disabled', 'now' => $this->now(), 'name' => $name]);
    }

    public function uninstall(string $name): void
    {
        $this->requireExists($name);

        $statement = $this->pdo->prepare(
            'UPDATE kontor_components SET status = :status, disabled_at = :now, updated_at = :now WHERE name = :name'
        );
        $statement->execute(['status' => 'uninstalled', 'now' => $this->now(), 'name' => $name]);
    }

    public function isEnabled(string $name): bool
    {
        $row = $this->find($name);

        return $row !== null && $row['status'] === 'enabled';
    }

    public function find(string $name): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_components WHERE name = :name');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_components ORDER BY name');

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function requireExists(string $name): void
    {
        if ($this->find($name) === null) {
            throw new RuntimeException("Component \"{$name}\" is not registered. Install it first.");
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
