<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use RuntimeException;

/**
 * Admin routes registered by business components under the single
 * ProcessKontor admin shell (kontor.md#6.3): components contribute routes,
 * they do not get their own Process module.
 */
final class RouteRegistry
{
    /**
     * @var array<string, array{controller: string, component: string}>
     */
    private array $routes = [];

    public function register(string $path, string $controller, string $component): void
    {
        $path = trim($path, '/');

        if (isset($this->routes[$path])) {
            throw new RuntimeException(
                "Route \"{$path}\" is already registered by \"{$this->routes[$path]['component']}\"."
            );
        }

        $this->routes[$path] = ['controller' => $controller, 'component' => $component];
    }

    /**
     * @return array{controller: string, component: string}|null
     */
    public function match(string $path): ?array
    {
        return $this->routes[trim($path, '/')] ?? null;
    }

    /**
     * @return array<string, array{controller: string, component: string}>
     */
    public function all(): array
    {
        return $this->routes;
    }
}
