<?php

declare(strict_types=1);

namespace Kontor\Mail\Infrastructure\Registry;

use Kontor\Mail\Contracts\InboundMailAdapterInterface;
use RuntimeException;

final class InboundMailAdapterRegistry
{
    /**
     * @var array<string, InboundMailAdapterInterface>
     */
    private array $adapters = [];

    public function register(InboundMailAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->key()] = $adapter;
    }

    public function has(string $key): bool
    {
        return isset($this->adapters[$key]);
    }

    public function get(string $key): InboundMailAdapterInterface
    {
        return $this->adapters[$key] ?? throw new RuntimeException("Inbound mail adapter \"{$key}\" is not registered.");
    }

    /**
     * @return array<string, InboundMailAdapterInterface>
     */
    public function all(): array
    {
        return $this->adapters;
    }
}
