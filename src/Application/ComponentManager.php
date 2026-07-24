<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\SDK\Contracts\ComponentInterface;
use Kontor\SDK\DTO\ComponentContext;

/**
 * Boots and registers ComponentInterface instances (kontor.md#9.1) and
 * records their lifecycle state via ComponentRegistry. This is the minimal
 * "local discovery + enable" slice of the full Component Manager
 * (Substage 1.3 adds ZIP install, dependency checks and uninstall).
 */
final class ComponentManager
{
    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly ?string $source = 'local',
    ) {
    }

    public function boot(ComponentInterface $component, ComponentContext $context): void
    {
        $this->registry->markInstalled($component->name(), $component->version(), $this->source);
        $this->registry->enable($component->name());

        $component->register($context);
        $component->boot($context);
    }

    public function disable(string $name): void
    {
        $this->registry->disable($name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function installed(): array
    {
        return $this->registry->all();
    }
}
