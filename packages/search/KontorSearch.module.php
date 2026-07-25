<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\Search\Application\SearchIndexDispatcher;
use Kontor\Search\Health\SearchHealthCheck;
use Kontor\Search\Infrastructure\Queue\SearchIndexJob;
use Kontor\Search\Infrastructure\Registry\SearchIndexerRegistry;
use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use Kontor\Search\Infrastructure\Search\ComponentsSearchProvider;
use Kontor\SDK\Contracts\SearchProviderInterface;

/**
 * KontorSearch bootstrap module (kontor.md Substage 2.4). Registers the
 * "search" capability (GlobalSearchService, implementing
 * Kontor\SDK\Contracts\SearchProviderInterface) into Kontor Core's
 * CapabilityRegistry, and wires the "search.index" job type into
 * KontorQueue's JobRegistry for asynchronous external-engine indexing.
 */
class KontorSearch extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Search',
            'summary' => 'Provider registry, federated global search, SQL full-text search and asynchronous indexing.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorSearch',
            'icon' => 'search',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorQueue'],
        ];
    }

    private ?SearchProviderRegistry $providerRegistry = null;
    private ?SearchIndexerRegistry $indexerRegistry = null;
    private ?GlobalSearchService $globalSearchService = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        /** @var KontorQueue $queueModule */
        $queueModule = $this->wire()->modules->get('KontorQueue');

        $this->providerRegistry()->register(
            new ComponentsSearchProvider($kontor->container()->get(ComponentRegistry::class))
        );

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'search',
            version: '1.0',
            contract: SearchProviderInterface::class,
            implementation: $this->globalSearchService(),
            component: 'KontorSearch',
        );

        $queueModule->jobRegistry()->register(
            'search.index',
            fn (array $payload): SearchIndexJob => new SearchIndexJob($payload, $this->indexerRegistry())
        );
    }

    public function providerRegistry(): SearchProviderRegistry
    {
        return $this->providerRegistry ??= new SearchProviderRegistry();
    }

    public function indexerRegistry(): SearchIndexerRegistry
    {
        return $this->indexerRegistry ??= new SearchIndexerRegistry();
    }

    public function globalSearchService(): GlobalSearchService
    {
        return $this->globalSearchService ??= new GlobalSearchService($this->providerRegistry());
    }

    public function indexDispatcher(): SearchIndexDispatcher
    {
        /** @var KontorQueue $queueModule */
        $queueModule = $this->wire()->modules->get('KontorQueue');

        return new SearchIndexDispatcher($queueModule->queue());
    }

    public function healthCheck(): SearchHealthCheck
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $organizations = $kontor->container()->get(OrganizationRepository::class);
        $organization = $organizations->defaultOrganization('US', 'en', 'EUR');

        return new SearchHealthCheck($this->globalSearchService(), $organization->uid->toString());
    }

    /**
     * No database table: SqlFullTextSearchProvider queries other
     * components' own tables directly (federated, not centralized), so
     * there is nothing here to migrate.
     */
    public function ___install(): void
    {
        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('search', self::getModuleInfo()['version'], 'search');
        $components->enable('search');
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Search module removed.'));
    }
}
