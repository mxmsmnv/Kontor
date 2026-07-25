<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Application;

use InvalidArgumentException;
use Kontor\Marketplace\Domain\Registry;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;

/**
 * The "official registry"/"custom registry" milestones' management side
 * (syncing itself is `RegistrySyncService`'s job). `"official"` is a
 * reserved name — `KontorMarketplace::___install()` seeds it once; this
 * service only ever adds/manages *custom* registries.
 */
final class RegistryManagementService
{
    public function __construct(
        private readonly RegistryRepository $registries,
    ) {
    }

    public function registerCustomRegistry(string $name, string $url, bool $trusted = false): Registry
    {
        if ($name === 'official') {
            throw new InvalidArgumentException('"official" is a reserved registry name.');
        }

        $registry = Registry::custom($name, $url, $trusted);
        $this->registries->save($registry);

        return $registry;
    }

    public function disable(string $name): void
    {
        $registry = $this->registries->require($name);
        $registry->disable();
        $this->registries->save($registry);
    }

    public function enable(string $name): void
    {
        $registry = $this->registries->require($name);
        $registry->enable();
        $this->registries->save($registry);
    }
}
