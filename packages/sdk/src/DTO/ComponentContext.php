<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

use Kontor\SDK\Contracts\CapabilityRegistryInterface;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Psr\Container\ContainerInterface;

/**
 * Handed to every component on boot() and register() (kontor.md#9.1).
 * Gives the component access to core services without coupling it to
 * ProcessWire internals directly.
 */
final class ComponentContext
{
    public function __construct(
        public readonly ContainerInterface $container,
        public readonly CapabilityRegistryInterface $capabilities,
        public readonly EventDispatcherInterface $events,
        public readonly string $organizationId,
        public readonly array $config = [],
    ) {
    }
}
