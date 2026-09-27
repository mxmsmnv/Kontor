<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeResponseRepository;
use Kontor\CRMIntake\Infrastructure\Settings\CRMIntakeSettingsProvider;
use Kontor\CRMIntake\Migrations\Migration0001CreateCRMIntakeTables;
use Kontor\Settings\Application\SettingsMigrationService;
use Kontor\Settings\Application\SettingsProviderRegistry;

final class CRMIntakeSettingsMigrationJourneyTest extends DatabaseTestCase
{
    private OrganizationRepository $organizations;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner($this->pdo))->run([
            new Migration0001CreateCRMIntakeTables(),
        ]);
        $this->organizations = new OrganizationRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_responses');
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_profiles');
        }

        parent::tearDown();
    }

    public function test_profile_moves_between_tenants_without_captured_answers_or_credentials(): void
    {
        $source = $this->organizations->defaultOrganization('US', 'en', 'USD');
        $target = Organization::createDefault('DE', 'de', 'EUR');
        $target->name = 'Migration target';
        $this->organizations->save($target);
        $sourceUid = $source->uid->toString();
        $targetUid = $target->uid->toString();

        $sourceIntake = $this->intake();
        $sourceIntake->configureDefault($sourceUid, 'Qualification', [[
            'key' => 'customer_context',
            'label' => 'Customer context',
            'type' => 'textarea',
            'targets' => ['lead', 'deal'],
            'required' => true,
        ]]);
        $sourceIntake->saveAnswers($sourceUid, 'lead', '01HSOURCELEAD000000000000', [
            'customer_context' => 'credential: sk-live-never-export',
        ]);

        $profile = $this->migration($sourceUid)->exportProfile(['host' => 'source.example.test']);
        $encoded = json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        self::assertSame(
            ['name', 'fields'],
            array_keys($profile['providers']['crm_intake']['data']),
        );
        self::assertStringNotContainsString('01HSOURCELEAD000000000000', $encoded);
        self::assertStringNotContainsString('sk-live-never-export', $encoded);

        $targetMigration = $this->migration($targetUid);
        $preview = $targetMigration->preview($profile);

        self::assertTrue($preview->successful());
        self::assertFalse($preview->applied);
        self::assertSame(1, $preview->changeCount());
        self::assertSame([], $sourceIntake->fieldsFor($targetUid, 'lead'));
        self::assertSame([], $sourceIntake->valuesFor($targetUid, 'lead', '01HSOURCELEAD000000000000'));

        $applied = $targetMigration->apply($profile);

        self::assertTrue($applied->successful());
        self::assertTrue($applied->applied);
        self::assertSame('customer_context', $sourceIntake->fieldsFor($targetUid, 'lead')[0]['key']);
        self::assertSame([], $sourceIntake->valuesFor($targetUid, 'lead', '01HSOURCELEAD000000000000'));
        self::assertSame(
            'credential: sk-live-never-export',
            $sourceIntake->valuesFor($sourceUid, 'lead', '01HSOURCELEAD000000000000')['customer_context'],
        );
    }

    private function intake(): CRMIntakeService
    {
        return new CRMIntakeService(
            new IntakeProfileRepository($this->pdo, $this->organizations),
            new IntakeResponseRepository($this->pdo, $this->organizations),
        );
    }

    private function migration(string $organizationUid): SettingsMigrationService
    {
        $profiles = new IntakeProfileRepository($this->pdo, $this->organizations);
        $service = new CRMIntakeService(
            $profiles,
            new IntakeResponseRepository($this->pdo, $this->organizations),
        );
        $registry = new SettingsProviderRegistry();
        $registry->register(new CRMIntakeSettingsProvider($organizationUid, $profiles, $service));

        return new SettingsMigrationService($registry);
    }
}
