<?php

declare(strict_types=1);

namespace Kontor\Cache\Tests\Unit\Infrastructure\Store;

use Kontor\Cache\Infrastructure\Store\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheStoreTest extends TestCase
{
    public function test_set_then_get(): void
    {
        $store = new InMemoryCacheStore();
        $store->set('key', 'value', null);

        $this->assertSame('value', $store->get('key'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull((new InMemoryCacheStore())->get('missing'));
    }

    public function test_delete_removes_the_key(): void
    {
        $store = new InMemoryCacheStore();
        $store->set('key', 'value', null);

        $store->delete('key');

        $this->assertNull($store->get('key'));
    }

    public function test_a_ttl_of_null_never_expires(): void
    {
        $store = new InMemoryCacheStore();
        $store->set('key', 'value', null);

        $this->assertSame('value', $store->get('key'));
    }

    public function test_an_expired_entry_reads_back_as_null(): void
    {
        $store = new InMemoryCacheStore();
        $store->set('key', 'value', -1);

        $this->assertNull($store->get('key'));
    }

    public function test_stores_arbitrary_value_types(): void
    {
        $store = new InMemoryCacheStore();
        $store->set('key', ['a' => 1, 'b' => [2, 3]], null);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $store->get('key'));
    }
}
