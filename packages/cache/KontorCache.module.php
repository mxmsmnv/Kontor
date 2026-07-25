<?php

namespace ProcessWire;

use Kontor\Cache\Health\CacheHealthCheck;
use Kontor\Cache\Infrastructure\CacheManager;
use Kontor\Cache\Infrastructure\Store\ProcessWireCacheStore;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\SDK\Contracts\CacheInterface;
use Kontor\SDK\Contracts\CacheStoreInterface;

/**
 * KontorCache bootstrap module (kontor.md Substage 2.3). Registers the
 * "cache" capability (Kontor\SDK\Contracts\CacheInterface, scoped to a
 * default "kontor" namespace) into Kontor Core's CapabilityRegistry.
 * Components that want their own namespace call manager()->forNamespace()
 * directly rather than going through the capability registry, since the
 * registry holds one object per capability name.
 *
 * No database table: unlike Core/Queue/Files, Cache has nothing to
 * persist as a record of truth (kontor.md#11 lists no kontor_cache table),
 * so there is nothing to migrate.
 */
class KontorCache extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Cache',
            'summary' => 'Namespaced, tag-invalidated caching over a swappable store.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorCache',
            'icon' => 'bolt',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
        ];
    }

    private ?CacheStoreInterface $store = null;
    private ?CacheManager $manager = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'cache',
            version: '1.0',
            contract: CacheInterface::class,
            implementation: $this->manager()->forNamespace('kontor'),
            component: 'KontorCache',
        );
    }

    public function store(): CacheStoreInterface
    {
        return $this->store ??= new ProcessWireCacheStore($this->wire()->cache);
    }

    public function manager(): CacheManager
    {
        return $this->manager ??= new CacheManager($this->store());
    }

    public function healthCheck(): CacheHealthCheck
    {
        return new CacheHealthCheck($this->store());
    }

    public function ___install(): void
    {
        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('cache', self::getModuleInfo()['version'], 'cache');
        $components->enable('cache');
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Cache module removed.'));
    }
}
