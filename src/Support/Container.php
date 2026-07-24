<?php

declare(strict_types=1);

namespace Kontor\Core\Support;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Minimal PSR-11 service container. All core services (registries,
 * dispatcher, repositories) are bound here and handed to components via
 * ComponentContext (kontor.md#9.1).
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function bind(string $id, callable $factory): void
    {
        unset($this->instances[$id]);
        $this->factories[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new class ("Service \"{$id}\" is not bound in the Kontor container.")
                extends \RuntimeException
                implements NotFoundExceptionInterface {
            };
        }

        try {
            $resolved = ($this->factories[$id])($this);
        } catch (\Throwable $e) {
            throw new class ($e->getMessage(), previous: $e)
                extends \RuntimeException
                implements ContainerExceptionInterface {
            };
        }

        $this->instances[$id] = $resolved;

        return $resolved;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || isset($this->factories[$id]);
    }
}
