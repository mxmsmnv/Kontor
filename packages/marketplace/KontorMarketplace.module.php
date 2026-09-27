<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Marketplace\Application\AdvisoryService;
use Kontor\Marketplace\Application\InstallabilityChecker;
use Kontor\Marketplace\Application\RegistryManagementService;
use Kontor\Marketplace\Application\RegistrySyncService;
use Kontor\Marketplace\Domain\Registry;
use Kontor\Marketplace\Health\MarketplaceHealthCheck;
use Kontor\Marketplace\Infrastructure\Http\CurlRegistryClient;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;
use Kontor\Marketplace\Infrastructure\Persistence\ListingRepository;
use Kontor\Marketplace\Infrastructure\Persistence\PublisherRepository;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;
use Kontor\Marketplace\Migrations\Migration0001CreateRegistriesTable;
use Kontor\Marketplace\Migrations\Migration0002CreatePublishersTable;
use Kontor\Marketplace\Migrations\Migration0003CreateListingsTable;
use Kontor\Marketplace\Migrations\Migration0004CreateAdvisoriesTable;

/**
 * KontorMarketplace bootstrap module (kontor.md Substage 8.3, third and
 * final component of Stage 8). Depends only on kontor/core — reuses
 * Core's own ComponentManifest/DependencyChecker/VersionConstraint
 * directly for parsing registry entries and checking installability,
 * rather than a parallel implementation.
 *
 * Instance-wide, not tenant-scoped: which components are available/
 * known to this Kontor installation isn't a per-organization concern,
 * the same reasoning kontor_components (kontor.md#11.2) already follows.
 */
class KontorMarketplace extends WireData implements Module
{
    /**
     * A placeholder default — this URL is never actually fetched by this
     * package's own tests (no outbound network access in this sandbox);
     * it follows the same `kontor.dev` convention every kontor.json's own
     * `$schema` field already uses.
     */
    private const OFFICIAL_REGISTRY_URL = 'https://kontor.dev/registry/official.json';

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Marketplace',
            'summary' => 'Official registry, custom registry, component metadata, advisories, publisher model.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorMarketplace',
            'icon' => 'shopping-cart',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-marketplace-registry-manage' => 'Add and manage marketplace registries',
                'kontor-marketplace-advisory-view' => 'View security advisories',
            ],
        ];
    }

    private ?RegistryRepository $registryRepository = null;
    private ?PublisherRepository $publisherRepository = null;
    private ?ListingRepository $listingRepository = null;
    private ?AdvisoryRepository $advisoryRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorMarketplace', $language, $strings);
        }
    }

    public function registryRepository(): RegistryRepository
    {
        return $this->registryRepository ??= new RegistryRepository($this->pdo());
    }

    public function publisherRepository(): PublisherRepository
    {
        return $this->publisherRepository ??= new PublisherRepository($this->pdo());
    }

    public function listingRepository(): ListingRepository
    {
        return $this->listingRepository ??= new ListingRepository($this->pdo());
    }

    public function advisoryRepository(): AdvisoryRepository
    {
        return $this->advisoryRepository ??= new AdvisoryRepository($this->pdo());
    }

    public function registryManagement(): RegistryManagementService
    {
        return new RegistryManagementService($this->registryRepository());
    }

    public function syncService(): RegistrySyncService
    {
        return new RegistrySyncService(
            new CurlRegistryClient(),
            $this->registryRepository(),
            $this->listingRepository(),
            $this->publisherRepository(),
            $this->advisoryRepository(),
        );
    }

    public function advisories(): AdvisoryService
    {
        return new AdvisoryService($this->advisoryRepository());
    }

    public function installabilityChecker(): InstallabilityChecker
    {
        return new InstallabilityChecker($this->advisories());
    }

    public function healthCheck(): MarketplaceHealthCheck
    {
        return new MarketplaceHealthCheck($this->registryRepository(), $this->listingRepository(), $this->advisories());
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateRegistriesTable(),
            new Migration0002CreatePublishersTable(),
            new Migration0003CreateListingsTable(),
            new Migration0004CreateAdvisoriesTable(),
        ]);

        (new RegistryRepository($pdo))->save(Registry::official(self::OFFICIAL_REGISTRY_URL));

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('marketplace', self::getModuleInfo()['version'], 'marketplace');
        $components->enable('marketplace');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('marketplace', self::getModuleInfo()['version'], 'marketplace');
        $components->enable('marketplace');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Marketplace module removed. Registries, listings and advisories were kept intact.'));
    }
}
