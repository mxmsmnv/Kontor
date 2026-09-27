<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Registry;

use Kontor\API\Contracts\ApiResourceInterface;
use RuntimeException;

final class ApiResourceRegistry
{
    /**
     * @var array<string, ApiResourceInterface>
     */
    private array $resources = [];

    public function register(ApiResourceInterface $resource): void
    {
        $this->resources[$resource->key()] = $resource;
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    public function get(string $key): ApiResourceInterface
    {
        return $this->resources[$key] ?? throw new RuntimeException("API resource \"{$key}\" is not registered.");
    }

    /**
     * @return array<string, ApiResourceInterface>
     */
    public function all(): array
    {
        return $this->resources;
    }
}
