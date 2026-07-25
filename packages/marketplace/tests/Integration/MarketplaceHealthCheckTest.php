<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Integration;

use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Marketplace\Application\AdvisoryService;
use Kontor\Marketplace\Domain\Advisory;
use Kontor\Marketplace\Domain\MarketplaceListing;
use Kontor\Marketplace\Domain\Registry;
use Kontor\Marketplace\Health\MarketplaceHealthCheck;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;
use Kontor\Marketplace\Infrastructure\Persistence\ListingRepository;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;
use Kontor\Marketplace\Migrations\Migration0001CreateRegistriesTable;
use Kontor\Marketplace\Migrations\Migration0003CreateListingsTable;
use Kontor\Marketplace\Migrations\Migration0004CreateAdvisoriesTable;

final class MarketplaceHealthCheckTest extends DatabaseTestCase
{
    protected function seedDefaultOrganization(): bool
    {
        return false;
    }

    protected function migrations(): array
    {
        return [
            new Migration0001CreateRegistriesTable(),
            new Migration0003CreateListingsTable(),
            new Migration0004CreateAdvisoriesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return [
            'kontor_marketplace_advisories',
            'kontor_marketplace_listings',
            'kontor_marketplace_registries',
            'kontor_migrations',
        ];
    }

    private function listing(): MarketplaceListing
    {
        return new MarketplaceListing(
            registryName: 'official',
            package: 'kontor/widgets',
            name: 'KontorWidgets',
            version: '0.1.0',
            title: 'Kontor Widgets',
            description: null,
            license: 'MIT',
            repositoryUrl: null,
            publisherName: 'Acme Inc',
            manifest: [],
            syncedAt: new \DateTimeImmutable(),
        );
    }

    public function test_ok_when_registries_are_freshly_synced_and_no_critical_advisory_applies(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $advisoryRepository = new AdvisoryRepository($this->pdo);

        $registry = Registry::official('https://example.com/official.json');
        $registry->recordSync();
        $registries->save($registry);
        $listings->save($this->listing());

        $result = (new MarketplaceHealthCheck($registries, $listings, new AdvisoryService($advisoryRepository)))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_warns_on_a_registry_that_has_never_synced(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $advisoryRepository = new AdvisoryRepository($this->pdo);

        $registries->save(Registry::official('https://example.com/official.json'));

        $result = (new MarketplaceHealthCheck($registries, $listings, new AdvisoryService($advisoryRepository)))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(['official'], $result->details['staleRegistries']);
    }

    public function test_warns_when_a_listing_is_affected_by_a_critical_advisory(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $advisoryRepository = new AdvisoryRepository($this->pdo);

        $registry = Registry::official('https://example.com/official.json');
        $registry->recordSync();
        $registries->save($registry);
        $listings->save($this->listing());
        $advisoryRepository->insert(Advisory::create('kontor/widgets', '<0.1.1', 'critical', 'RCE'));

        $result = (new MarketplaceHealthCheck($registries, $listings, new AdvisoryService($advisoryRepository)))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(['kontor/widgets'], $result->details['criticalAdvisoryPackages']);
    }
}
