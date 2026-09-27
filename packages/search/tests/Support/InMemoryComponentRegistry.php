<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Support;

use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;

final class InMemoryComponentRegistry implements ComponentRegistryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function markInstalled(string $name, string $version, ?string $source = null, ?string $checksum = null): void
    {
        $this->rows[$name] = [
            'name' => $name,
            'version' => $version,
            'status' => $this->rows[$name]['status'] ?? 'installed',
            'source' => $source,
            'checksum' => $checksum,
        ];
    }

    public function enable(string $name): void
    {
        $this->rows[$name]['status'] = 'enabled';
    }

    public function disable(string $name): void
    {
        $this->rows[$name]['status'] = 'disabled';
    }

    public function uninstall(string $name): void
    {
        $this->rows[$name]['status'] = 'uninstalled';
    }

    public function isEnabled(string $name): bool
    {
        return ($this->rows[$name]['status'] ?? null) === 'enabled';
    }

    public function find(string $name): ?array
    {
        return $this->rows[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->rows);
    }
}
