<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

/**
 * Adapter contract for component lifecycle storage (kontor.md Substage 1.3
 * "registry adapter" milestone). ComponentRegistry is the MySQL-backed
 * implementation; ComponentManager depends on this interface so tests can
 * substitute an in-memory adapter.
 */
interface ComponentRegistryInterface
{
    public function markInstalled(string $name, string $version, ?string $source = null, ?string $checksum = null): void;

    public function enable(string $name): void;

    public function disable(string $name): void;

    /**
     * Marks a component as uninstalled without deleting its ledger row or
     * touching any data the component owns (codex rule #10).
     */
    public function uninstall(string $name): void;

    public function isEnabled(string $name): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $name): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;
}
