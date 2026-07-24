<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\Core\Support\VersionConstraint;
use Kontor\SDK\Contracts\CapabilityRegistryInterface;
use RuntimeException;

/**
 * In-memory capability registry (kontor.md#9.2). Components register the
 * capabilities they implement so other components can discover and consume
 * them without a hard dependency on the providing component's classes.
 */
final class CapabilityRegistry implements CapabilityRegistryInterface
{
    /**
     * @var array<string, array{version: string, contract: string, implementation: object, component: string}>
     */
    private array $capabilities = [];

    public function register(
        string $capability,
        string $version,
        string $contract,
        object $implementation,
        string $component
    ): void {
        if (!($implementation instanceof $contract)) {
            throw new RuntimeException(
                "Component \"{$component}\" registered capability \"{$capability}\" ".
                "with an implementation that does not implement \"{$contract}\"."
            );
        }

        $this->capabilities[$capability] = [
            'version' => $version,
            'contract' => $contract,
            'implementation' => $implementation,
            'component' => $component,
        ];
    }

    public function has(string $capability, ?string $constraint = null): bool
    {
        if (!isset($this->capabilities[$capability])) {
            return false;
        }

        if ($constraint === null) {
            return true;
        }

        return VersionConstraint::satisfies($this->capabilities[$capability]['version'], $constraint);
    }

    public function get(string $capability, ?string $constraint = null): object
    {
        if (!$this->has($capability, $constraint)) {
            throw new RuntimeException(
                "Capability \"{$capability}\" is not registered".
                ($constraint !== null ? " for constraint \"{$constraint}\"." : '.')
            );
        }

        return $this->capabilities[$capability]['implementation'];
    }

    public function all(): array
    {
        return array_map(
            static fn (array $entry): object => $entry['implementation'],
            $this->capabilities
        );
    }
}
