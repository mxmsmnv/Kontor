<?php

declare(strict_types=1);

namespace Kontor\Cache\Tests\Unit\Infrastructure;

use Kontor\Cache\Infrastructure\CacheManager;
use Kontor\Cache\Infrastructure\Store\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

final class TaggedCacheTest extends TestCase
{
    public function test_set_then_get(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');
        $cache->set('lead-count', 42);

        $this->assertSame(42, $cache->get('lead-count'));
    }

    public function test_get_returns_default_on_miss(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $this->assertSame('fallback', $cache->get('missing', default: 'fallback'));
    }

    public function test_has(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $this->assertFalse($cache->has('lead-count'));
        $cache->set('lead-count', 1);
        $this->assertTrue($cache->has('lead-count'));
    }

    public function test_delete(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');
        $cache->set('lead-count', 1);

        $cache->delete('lead-count');

        $this->assertFalse($cache->has('lead-count'));
    }

    public function test_remember_computes_once_and_caches(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');
        $calls = 0;
        $factory = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $first = $cache->remember('key', $factory);
        $second = $cache->remember('key', $factory);

        $this->assertSame('computed', $first);
        $this->assertSame('computed', $second);
        $this->assertSame(1, $calls);
    }

    public function test_two_namespaces_do_not_collide_on_the_same_key(): void
    {
        $store = new InMemoryCacheStore();
        $manager = new CacheManager($store);

        $crm = $manager->forNamespace('KontorCRM');
        $sales = $manager->forNamespace('KontorSales');

        $crm->set('count', 'crm-value');
        $sales->set('count', 'sales-value');

        $this->assertSame('crm-value', $crm->get('count'));
        $this->assertSame('sales-value', $sales->get('count'));
    }

    public function test_flush_namespace_invalidates_only_that_namespace(): void
    {
        $store = new InMemoryCacheStore();
        $manager = new CacheManager($store);

        $crm = $manager->forNamespace('KontorCRM');
        $sales = $manager->forNamespace('KontorSales');
        $crm->set('count', 'crm-value');
        $sales->set('count', 'sales-value');

        $crm->flushNamespace();

        $this->assertFalse($crm->has('count'));
        $this->assertTrue($sales->has('count'));
    }

    public function test_flush_tag_invalidates_only_entries_stored_with_that_tag(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $cache->set('deal-1', 'value-1', tags: ['deal']);
        $cache->set('deal-2', 'value-2', tags: ['deal']);
        $cache->set('lead-1', 'value-3', tags: ['lead']);

        $cache->flushTag('deal');

        $this->assertNull($cache->get('deal-1', tags: ['deal']));
        $this->assertNull($cache->get('deal-2', tags: ['deal']));
        $this->assertSame('value-3', $cache->get('lead-1', tags: ['lead']));
    }

    public function test_the_same_key_with_different_tags_is_a_distinct_entry(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $cache->set('report', 'tagged-with-deal', tags: ['deal']);
        $cache->set('report', 'tagged-with-lead', tags: ['lead']);

        $this->assertSame('tagged-with-deal', $cache->get('report', tags: ['deal']));
        $this->assertSame('tagged-with-lead', $cache->get('report', tags: ['lead']));
    }

    public function test_flushing_a_tag_does_not_disturb_an_untagged_entry_with_the_same_key(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $cache->set('report', 'untagged-value');
        $cache->set('report', 'tagged-value', tags: ['deal']);

        $cache->flushTag('deal');

        $this->assertSame('untagged-value', $cache->get('report'));
        $this->assertNull($cache->get('report', tags: ['deal']));
    }

    public function test_flush_tag_is_order_independent(): void
    {
        $cache = (new CacheManager(new InMemoryCacheStore()))->forNamespace('KontorCRM');

        $cache->set('report', 'value', tags: ['a', 'b']);

        $this->assertSame('value', $cache->get('report', tags: ['b', 'a']));
    }
}
