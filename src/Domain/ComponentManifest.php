<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

/**
 * Parsed kontor.json (kontor.md#22). Every field the manifest requirement
 * list (section 22.2) declares mandatory is validated in fromArray() —
 * an incomplete manifest fails fast at discovery/install time rather than
 * surfacing as a confusing error deeper in the boot sequence.
 */
final class ComponentManifest
{
    /**
     * @param array<string, string> $requires
     * @param array<string, string> $suggests
     * @param array<string, string> $conflicts
     * @param string[] $permissions
     * @param string[] $storageTables
     * @param string[] $storageDirectories
     * @param array<string, mixed> $raw the full decoded manifest, for fields not modeled explicitly
     */
    private function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $package,
        public readonly string $namespace,
        public readonly ?string $processWireModule,
        public readonly array $requires,
        public readonly array $suggests,
        public readonly array $conflicts,
        public readonly array $permissions,
        public readonly array $storageTables,
        public readonly array $storageDirectories,
        public readonly ?string $migrationsPath,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['name', 'version', 'package', 'namespace', 'requires'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new InvalidManifestException("kontor.json is missing required field \"{$required}\".");
            }
        }

        if (!is_array($data['requires'])) {
            throw new InvalidManifestException('kontor.json "requires" must be an object.');
        }

        return new self(
            name: (string) $data['name'],
            version: (string) $data['version'],
            package: (string) $data['package'],
            namespace: (string) $data['namespace'],
            processWireModule: isset($data['processWireModule']) ? (string) $data['processWireModule'] : null,
            requires: $data['requires'],
            suggests: is_array($data['suggests'] ?? null) ? $data['suggests'] : [],
            conflicts: is_array($data['conflicts'] ?? null) ? $data['conflicts'] : [],
            permissions: is_array($data['permissions'] ?? null) ? $data['permissions'] : [],
            storageTables: is_array($data['storage']['tables'] ?? null) ? $data['storage']['tables'] : [],
            storageDirectories: is_array($data['storage']['directories'] ?? null) ? $data['storage']['directories'] : [],
            migrationsPath: isset($data['migrations']['path']) ? (string) $data['migrations']['path'] : null,
            raw: $data,
        );
    }
}
