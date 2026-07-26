<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Support;

use Kontor\SDK\Contracts\CacheInterface;

final class RecordingCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var string[] */
    public array $flushedTags = [];

    public int $rememberMisses = 0;

    public function get(string $key, array $tags = [], mixed $default = null): mixed
    {
        return $this->values[$this->route($key, $tags)] ?? $default;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null, array $tags = []): void
    {
        $this->values[$this->route($key, $tags)] = $value;
    }

    public function has(string $key, array $tags = []): bool
    {
        return array_key_exists($this->route($key, $tags), $this->values);
    }

    public function delete(string $key, array $tags = []): void
    {
        unset($this->values[$this->route($key, $tags)]);
    }

    public function remember(string $key, callable $factory, ?int $ttlSeconds = null, array $tags = []): mixed
    {
        if ($this->has($key, $tags)) {
            return $this->get($key, $tags);
        }

        $this->rememberMisses++;
        $value = $factory();
        $this->set($key, $value, $ttlSeconds, $tags);

        return $value;
    }

    public function flushTag(string $tag): void
    {
        $this->flushedTags[] = $tag;
        $this->values = [];
    }

    public function flushNamespace(): void
    {
        $this->values = [];
    }

    /**
     * @param string[] $tags
     */
    private function route(string $key, array $tags): string
    {
        sort($tags);

        return $key . '|' . implode(',', $tags);
    }
}
