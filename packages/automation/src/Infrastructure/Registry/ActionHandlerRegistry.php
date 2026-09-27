<?php

declare(strict_types=1);

namespace Kontor\Automation\Infrastructure\Registry;

use Kontor\Automation\Contracts\ActionHandlerInterface;
use RuntimeException;

/**
 * The "actions" milestone's registry. Mirrors
 * Kontor\Core\Infrastructure\Registry\ReportProviderRegistry's
 * register/has/get-throws/all shape. Other components register their own
 * action handlers by depending on kontor/automation and calling
 * register() during their own module init — only one built-in handler
 * ships with this package (LogActionHandler, proving the pipeline); see
 * the README.
 */
final class ActionHandlerRegistry
{
    /**
     * @var array<string, ActionHandlerInterface>
     */
    private array $handlers = [];

    public function register(ActionHandlerInterface $handler): void
    {
        $this->handlers[$handler->key()] = $handler;
    }

    public function has(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    public function get(string $key): ActionHandlerInterface
    {
        return $this->handlers[$key]
            ?? throw new RuntimeException("No action handler is registered for key \"{$key}\".");
    }

    /**
     * @return array<string, ActionHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
