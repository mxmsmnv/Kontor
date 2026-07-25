<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Integration;

use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Marketplace\Application\RegistrySyncService;
use Kontor\Marketplace\Contracts\RegistryClientInterface;
use Kontor\Marketplace\Domain\Registry;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;
use Kontor\Marketplace\Infrastructure\Persistence\ListingRepository;
use Kontor\Marketplace\Infrastructure\Persistence\PublisherRepository;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;
use Kontor\Marketplace\Migrations\Migration0001CreateRegistriesTable;
use Kontor\Marketplace\Migrations\Migration0002CreatePublishersTable;
use Kontor\Marketplace\Migrations\Migration0003CreateListingsTable;
use Kontor\Marketplace\Migrations\Migration0004CreateAdvisoriesTable;

/**
 * The third real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core`, after `kontor/api` and `kontor/graphql`. Uses a
 * fake `RegistryClientInterface` since this sandbox has no outbound
 * network access — see that interface's own doc comment.
 */
final class RegistrySyncServiceTest extends DatabaseTestCase
{
    protected function seedDefaultOrganization(): bool
    {
        return false;
    }

    protected function migrations(): array
    {
        return [
            new Migration0001CreateRegistriesTable(),
            new Migration0002CreatePublishersTable(),
            new Migration0003CreateListingsTable(),
            new Migration0004CreateAdvisoriesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return [
            'kontor_marketplace_advisories',
            'kontor_marketplace_listings',
            'kontor_marketplace_publishers',
            'kontor_marketplace_registries',
            'kontor_migrations',
        ];
    }

    private function fakeClient(string $payload): RegistryClientInterface
    {
        return new class($payload) implements RegistryClientInterface {
            public function __construct(private readonly string $payload)
            {
            }

            public function fetch(string $url): string
            {
                return $this->payload;
            }
        };
    }

    private function payload(): string
    {
        return json_encode([
            'components' => [
                [
                    'name' => 'KontorWidgets',
                    'version' => '0.1.0',
                    'package' => 'kontor/widgets',
                    'namespace' => 'Kontor\\Widgets',
                    'requires' => ['php' => '>=8.2'],
                    'title' => 'Kontor Widgets',
                    'description' => 'Sample widgets.',
                    'license' => 'MIT',
                    'repository' => 'https://github.com/example/widgets',
                    'author' => ['name' => 'Acme Inc', 'url' => 'https://acme.example'],
                ],
                [
                    // Missing "requires" — malformed, should be skipped.
                    'name' => 'KontorBroken',
                    'version' => '0.1.0',
                    'package' => 'kontor/broken',
                    'namespace' => 'Kontor\\Broken',
                ],
            ],
            'advisories' => [
                [
                    'package' => 'kontor/widgets',
                    'affectedVersions' => '<0.1.1',
                    'severity' => 'high',
                    'title' => 'XSS in widget renderer',
                    'publishedAt' => '2026-01-01T00:00:00+00:00',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function test_syncing_a_trusted_registry_registers_listings_verifies_publisher_and_ingests_advisories(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $publishers = new PublisherRepository($this->pdo);
        $advisories = new AdvisoryRepository($this->pdo);

        $registries->save(Registry::official('https://example.com/official.json'));

        $service = new RegistrySyncService($this->fakeClient($this->payload()), $registries, $listings, $publishers, $advisories);
        $result = $service->sync('official');

        $this->assertSame(1, $result->listingsSynced);
        $this->assertSame(1, $result->advisoriesSynced);
        $this->assertCount(1, $result->skipped);
        $this->assertStringContainsString('component:', $result->skipped[0]);

        $listing = $listings->find('official', 'kontor/widgets');
        $this->assertNotNull($listing);
        $this->assertSame('Kontor Widgets', $listing->title);
        $this->assertSame('Acme Inc', $listing->publisherName);

        $publisher = $publishers->find('Acme Inc');
        $this->assertNotNull($publisher);
        $this->assertTrue($publisher->verified);

        $this->assertNotNull($registries->require('official')->lastSyncedAt);
    }

    public function test_syncing_an_untrusted_custom_registry_does_not_verify_the_publisher(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $publishers = new PublisherRepository($this->pdo);
        $advisories = new AdvisoryRepository($this->pdo);

        $registries->save(Registry::custom('third-party', 'https://example.com/third-party.json'));

        $service = new RegistrySyncService($this->fakeClient($this->payload()), $registries, $listings, $publishers, $advisories);
        $service->sync('third-party');

        $this->assertFalse($publishers->find('Acme Inc')->verified);
    }

    public function test_a_publisher_already_verified_stays_verified_after_an_untrusted_sync(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $publishers = new PublisherRepository($this->pdo);
        $advisories = new AdvisoryRepository($this->pdo);

        $registries->save(Registry::official('https://example.com/official.json'));
        $registries->save(Registry::custom('third-party', 'https://example.com/third-party.json'));

        $service = new RegistrySyncService($this->fakeClient($this->payload()), $registries, $listings, $publishers, $advisories);
        $service->sync('official');
        $service->sync('third-party');

        $this->assertTrue($publishers->find('Acme Inc')->verified);
    }

    public function test_re_syncing_the_same_payload_does_not_duplicate_advisories(): void
    {
        $registries = new RegistryRepository($this->pdo);
        $listings = new ListingRepository($this->pdo);
        $publishers = new PublisherRepository($this->pdo);
        $advisories = new AdvisoryRepository($this->pdo);

        $registries->save(Registry::official('https://example.com/official.json'));

        $service = new RegistrySyncService($this->fakeClient($this->payload()), $registries, $listings, $publishers, $advisories);
        $first = $service->sync('official');
        $second = $service->sync('official');

        $this->assertSame(1, $first->advisoriesSynced);
        $this->assertSame(0, $second->advisoriesSynced);
        $this->assertCount(1, $advisories->forPackage('kontor/widgets'));
    }
}
