<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

interface CapabilityRegistryInterface
{
    public function register(
        string $capability,
        string $version,
        string $contract,
        object $implementation,
        string $component
    ): void;

    public function has(string $capability, ?string $constraint = null): bool;

    public function get(string $capability, ?string $constraint = null): object;

    /**
     * @return array<string, object>
     */
    public function all(): array;
}
