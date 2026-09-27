<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Integration;

use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Marketplace\Application\AdvisoryService;
use Kontor\Marketplace\Application\InstallabilityChecker;
use Kontor\Marketplace\Domain\Advisory;
use Kontor\Marketplace\Domain\MarketplaceListing;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;
use Kontor\Marketplace\Migrations\Migration0004CreateAdvisoriesTable;

final class InstallabilityCheckerTest extends DatabaseTestCase
{
    protected function seedDefaultOrganization(): bool
    {
        return false;
    }

    protected function migrations(): array
    {
        return [new Migration0004CreateAdvisoriesTable()];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_marketplace_advisories', 'kontor_migrations'];
    }

    private function listing(string $version): MarketplaceListing
    {
        return new MarketplaceListing(
            registryName: 'official',
            package: 'kontor/widgets',
            name: 'KontorWidgets',
            version: $version,
            title: 'Kontor Widgets',
            description: null,
            license: 'MIT',
            repositoryUrl: null,
            publisherName: 'Acme Inc',
            manifest: [
                'name' => 'KontorWidgets',
                'version' => $version,
                'package' => 'kontor/widgets',
                'namespace' => 'Kontor\\Widgets',
                'requires' => ['php' => '>=8.2'],
            ],
            syncedAt: new \DateTimeImmutable(),
        );
    }

    public function test_installable_when_dependencies_are_satisfied_and_no_critical_advisory_applies(): void
    {
        $advisories = new AdvisoryRepository($this->pdo);
        $checker = new InstallabilityChecker(new AdvisoryService($advisories));

        $result = $checker->check($this->listing('0.1.0'), [], PHP_VERSION, '3.0.240');

        $this->assertTrue($result->isInstallable());
        $this->assertTrue($result->dependencies->satisfied);
        $this->assertSame([], $result->advisories);
    }

    public function test_not_installable_when_a_required_dependency_is_missing(): void
    {
        $advisories = new AdvisoryRepository($this->pdo);
        $checker = new InstallabilityChecker(new AdvisoryService($advisories));

        $result = $checker->check($this->listing('0.1.0'), [], '7.4.0', '3.0.240');

        $this->assertFalse($result->isInstallable());
        $this->assertFalse($result->dependencies->satisfied);
    }

    public function test_a_critical_advisory_blocks_installability_even_though_dependencies_are_satisfied(): void
    {
        $advisoryRepository = new AdvisoryRepository($this->pdo);
        $advisoryRepository->insert(Advisory::create('kontor/widgets', '<0.1.1', 'critical', 'RCE in widget renderer'));

        $checker = new InstallabilityChecker(new AdvisoryService($advisoryRepository));
        $result = $checker->check($this->listing('0.1.0'), [], PHP_VERSION, '3.0.240');

        $this->assertFalse($result->isInstallable());
        $this->assertTrue($result->dependencies->satisfied);
        $this->assertTrue($result->hasCriticalAdvisory());
        $this->assertCount(1, $result->advisories);
    }

    public function test_a_non_critical_advisory_is_surfaced_but_does_not_block_installability(): void
    {
        $advisoryRepository = new AdvisoryRepository($this->pdo);
        $advisoryRepository->insert(Advisory::create('kontor/widgets', '<0.1.1', 'low', 'Minor cosmetic bug'));

        $checker = new InstallabilityChecker(new AdvisoryService($advisoryRepository));
        $result = $checker->check($this->listing('0.1.0'), [], PHP_VERSION, '3.0.240');

        $this->assertTrue($result->isInstallable());
        $this->assertCount(1, $result->advisories);
    }
}
