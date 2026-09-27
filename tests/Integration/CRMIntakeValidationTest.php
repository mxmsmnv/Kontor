<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeResponseRepository;
use Kontor\CRMIntake\Migrations\Migration0001CreateCRMIntakeTables;

final class CRMIntakeValidationTest extends DatabaseTestCase
{
    private OrganizationRepository $organizations;
    private CRMIntakeService $service;
    private string $organizationUid;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner($this->pdo))->run([
            new Migration0001CreateCRMIntakeTables(),
        ]);
        $this->organizations = new OrganizationRepository($this->pdo);
        $this->organizationUid = $this->organizations
            ->defaultOrganization('US', 'en', 'USD')
            ->uid
            ->toString();
        $this->service = new CRMIntakeService(
            new IntakeProfileRepository($this->pdo, $this->organizations),
            new IntakeResponseRepository($this->pdo, $this->organizations),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_responses');
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_crm_intake_profiles');
        }

        parent::tearDown();
    }

    public function test_profile_name_must_be_present_and_fit_storage_limit(): void
    {
        foreach (['   ', str_repeat('x', 192)] as $invalidName) {
            try {
                $this->service->configureDefault($this->organizationUid, $invalidName, $this->validFields());
                self::fail('An invalid profile name must be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('profile name', $exception->getMessage());
            }
        }

        self::assertSame(0, $this->profileCount());
    }

    public function test_profile_rejects_invalid_schema_variants(): void
    {
        $invalidProfiles = [
            [],
            [['key' => 'A', 'label' => 'Label', 'targets' => ['lead']]],
            [
                ['key' => 'duplicate', 'label' => 'First', 'targets' => ['lead']],
                ['key' => 'duplicate', 'label' => 'Second', 'targets' => ['deal']],
            ],
            [['key' => 'bad_type', 'label' => 'Bad type', 'type' => 'number', 'targets' => ['lead']]],
            [['key' => 'bad_target', 'label' => 'Bad target', 'targets' => ['invoice']]],
            [['key' => 'no_options', 'label' => 'No options', 'type' => 'select', 'targets' => ['lead']]],
            [[
                'key' => 'bad_option',
                'label' => 'Bad option',
                'type' => 'select',
                'targets' => ['lead'],
                'options' => ['!' => 'Invalid'],
            ]],
        ];

        foreach ($invalidProfiles as $index => $fields) {
            try {
                $this->service->configureDefault(
                    $this->organizationUid,
                    'Invalid ' . $index,
                    $fields,
                );
                self::fail("Invalid profile variant {$index} must be rejected.");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(0, $this->profileCount());
    }

    public function test_answers_reject_required_and_typed_value_failures_without_persisting(): void
    {
        $this->configureProfile();
        $invalidAnswers = [
            ['website' => 'https://example.test', 'stage' => 'new', 'interests' => ['audit'], 'meeting_at' => '2026-09-27T10:30'],
            ['company' => 'Acme', 'website' => 'ftp://example.test', 'stage' => 'new', 'interests' => ['audit'], 'meeting_at' => '2026-09-27T10:30'],
            ['company' => 'Acme', 'website' => 'https://example.test', 'stage' => 'unknown', 'interests' => ['audit'], 'meeting_at' => '2026-09-27T10:30'],
            ['company' => 'Acme', 'website' => 'https://example.test', 'stage' => 'new', 'interests' => ['unsupported'], 'meeting_at' => '2026-09-27T10:30'],
            ['company' => 'Acme', 'website' => 'https://example.test', 'stage' => 'new', 'interests' => ['audit'], 'meeting_at' => 'not-a-date'],
        ];

        foreach ($invalidAnswers as $index => $answers) {
            try {
                $this->service->saveAnswers(
                    $this->organizationUid,
                    'lead',
                    '01HLEAD00000000000000000' . $index,
                    $answers,
                );
                self::fail("Invalid answer variant {$index} must be rejected.");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(0, $this->responseCount());
    }

    public function test_valid_answers_are_normalized_and_unknown_input_is_discarded(): void
    {
        $this->configureProfile();

        $response = $this->service->saveAnswers(
            $this->organizationUid,
            'lead',
            '01HLEAD000000000000000001',
            [
                'company' => '  Acme GmbH  ',
                'website' => ' https://example.test/path ',
                'stage' => ' NEW ',
                'interests' => ['Audit', 'audit', 'delivery'],
                'meeting_at' => '2026-09-27 10:30:45',
                'unknown' => 'must not persist',
            ],
        );

        self::assertSame('Acme GmbH', $response->answers['company']);
        self::assertSame('https://example.test/path', $response->answers['website']);
        self::assertSame('new', $response->answers['stage']);
        self::assertSame(['audit', 'delivery'], $response->answers['interests']);
        self::assertSame('2026-09-27T10:30', $response->answers['meeting_at']);
        self::assertArrayNotHasKey('unknown', $response->answers);
    }

    public function test_responses_and_profiles_are_isolated_by_organization(): void
    {
        $this->configureProfile();
        $this->service->saveAnswers(
            $this->organizationUid,
            'lead',
            '01HSHAREDENTITY00000000000',
            $this->validAnswers('Primary tenant'),
        );

        $second = Organization::createDefault('DE', 'de', 'EUR');
        $second->name = 'Second tenant';
        $this->organizations->save($second);
        $secondUid = $second->uid->toString();

        self::assertSame([], $this->service->fieldsFor($secondUid, 'lead'));
        self::assertSame([], $this->service->valuesFor(
            $secondUid,
            'lead',
            '01HSHAREDENTITY00000000000',
        ));

        $this->service->configureDefault($secondUid, 'Second profile', $this->validFields());
        $this->service->saveAnswers(
            $secondUid,
            'lead',
            '01HSHAREDENTITY00000000000',
            $this->validAnswers('Second tenant'),
        );

        self::assertSame('Primary tenant', $this->service->valuesFor(
            $this->organizationUid,
            'lead',
            '01HSHAREDENTITY00000000000',
        )['company']);
        self::assertSame('Second tenant', $this->service->valuesFor(
            $secondUid,
            'lead',
            '01HSHAREDENTITY00000000000',
        )['company']);
    }

    public function test_unsupported_entity_types_and_missing_profiles_are_rejected(): void
    {
        foreach (['invoice', '', 'LEAD-invalid'] as $entityType) {
            try {
                $this->service->valuesFor($this->organizationUid, $entityType, 'entity');
                self::fail("Unsupported entity type {$entityType} must be rejected.");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No active CRM intake profile');
        $this->service->saveAnswers($this->organizationUid, 'lead', 'entity', []);
    }

    /** @return array<int, array<string, mixed>> */
    private function validFields(): array
    {
        return [
            ['key' => 'company', 'label' => 'Company', 'targets' => ['lead'], 'required' => true],
            ['key' => 'website', 'label' => 'Website', 'type' => 'url', 'targets' => ['lead']],
            [
                'key' => 'stage',
                'label' => 'Stage',
                'type' => 'select',
                'targets' => ['lead'],
                'options' => ['new' => 'New', 'qualified' => 'Qualified'],
            ],
            [
                'key' => 'interests',
                'label' => 'Interests',
                'type' => 'multiselect',
                'targets' => ['lead'],
                'options' => ['audit' => 'Audit', 'delivery' => 'Delivery'],
            ],
            ['key' => 'meeting_at', 'label' => 'Meeting', 'type' => 'datetime', 'targets' => ['lead']],
        ];
    }

    /** @return array<string, mixed> */
    private function validAnswers(string $company): array
    {
        return [
            'company' => $company,
            'website' => 'https://example.test',
            'stage' => 'new',
            'interests' => ['audit'],
            'meeting_at' => '2026-09-27T10:30',
        ];
    }

    private function configureProfile(): void
    {
        $this->service->configureDefault(
            $this->organizationUid,
            'Qualification',
            $this->validFields(),
        );
    }

    private function profileCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_crm_intake_profiles')->fetchColumn();
    }

    private function responseCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_crm_intake_responses')->fetchColumn();
    }
}
