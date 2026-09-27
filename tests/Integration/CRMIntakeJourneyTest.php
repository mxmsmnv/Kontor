<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRM\Application\CRMService;
use Kontor\CRM\Application\LeadConversionService;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\CRM\Migrations\Migration0001CreateLeadsTable;
use Kontor\CRM\Migrations\Migration0002CreatePipelinesTable;
use Kontor\CRM\Migrations\Migration0003CreateStagesTable;
use Kontor\CRM\Migrations\Migration0004CreateDealsTable;
use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeResponseRepository;
use Kontor\CRMIntake\Migrations\Migration0001CreateCRMIntakeTables;

final class CRMIntakeJourneyTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        $this->dropJourneyTablesIfAvailable();
        parent::setUp();

        (new MigrationRunner($this->pdo))->run([
            new Migration0001CreateLeadsTable(),
            new Migration0002CreatePipelinesTable(),
            new Migration0003CreateStagesTable(),
            new Migration0004CreateDealsTable(),
            new Migration0001CreateCRMIntakeTables(),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropJourneyTables();
        }

        parent::tearDown();
    }

    public function test_qualification_answers_follow_a_lead_into_its_deal(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organizationUid = $organizations
            ->defaultOrganization('US', 'en', 'USD')
            ->uid
            ->toString();

        $intake = new CRMIntakeService(
            new IntakeProfileRepository($this->pdo, $organizations),
            new IntakeResponseRepository($this->pdo, $organizations),
        );
        $intake->configureDefault($organizationUid, 'E2E Qualification', [
            [
                'key' => 'source_channel',
                'label' => 'Source channel',
                'type' => 'select',
                'targets' => ['lead', 'deal'],
                'options' => ['referral' => 'Referral', 'website' => 'Website'],
                'required' => true,
                'binding' => 'source',
            ],
            [
                'key' => 'budget_scope',
                'label' => 'Budget scope',
                'type' => 'select',
                'targets' => ['lead', 'deal'],
                'options' => ['growth' => '10k-50k', 'enterprise' => 'Over 50k'],
                'required' => true,
            ],
            [
                'key' => 'contact_context',
                'label' => 'Contact context',
                'type' => 'textarea',
                'targets' => ['contact'],
            ],
        ]);

        $pipelines = new PipelineRepository($this->pdo, $organizations);
        $stages = new StageRepository($this->pdo);
        $pipeline = Pipeline::create($organizationUid, 'Sales', isDefault: true);
        $pipelines->save($pipeline);
        $stage = Stage::create(
            $pipeline->uid->toString(),
            'qualified',
            ['en' => 'Qualified'],
            probability: 25,
            sortOrder: 10,
        );
        $stages->save($stage);

        $leads = new LeadRepository($this->pdo, $organizations);
        $deals = new DealRepository($this->pdo, $organizations);
        $lead = Lead::create(
            $organizationUid,
            'E2E workflow modernization',
            contactUid: '01HCONTACT0000000000000000',
            source: 'referral',
        );
        $lead->status = 'qualified';
        $leads->save($lead);
        $intake->saveAnswers($organizationUid, 'lead', $lead->uid->toString(), [
            'source_channel' => 'referral',
            'budget_scope' => 'growth',
            'contact_context' => 'must not cross the lead boundary',
        ]);

        $crm = new CRMService(
            new LeadConversionService($leads, $pipelines, $stages, $deals),
            $deals,
            $stages,
        );
        $dealUid = $crm->convertLead($lead->uid->toString(), 'e2e-admin');
        $intake->copyAnswers(
            $organizationUid,
            'lead',
            $lead->uid->toString(),
            'deal',
            $dealUid,
        );

        $convertedLead = $leads->require($lead->uid->toString());
        $deal = $deals->require($dealUid);

        self::assertSame('converted', $convertedLead->status);
        self::assertSame($dealUid, $convertedLead->convertedDealUid);
        self::assertSame('referral', $deal->source);
        self::assertSame($lead->contactUid, $deal->contactUid);
        $dealAnswers = $intake->valuesFor($organizationUid, 'deal', $dealUid);
        self::assertCount(2, $dealAnswers);
        self::assertSame('referral', $dealAnswers['source_channel']);
        self::assertSame('growth', $dealAnswers['budget_scope']);
        self::assertArrayNotHasKey('contact_context', $dealAnswers);
    }

    private function dropJourneyTablesIfAvailable(): void
    {
        $dsn = getenv('KONTOR_TEST_DB_DSN');

        if ($dsn === false) {
            return;
        }

        $pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $this->dropJourneyTables($pdo);
    }

    private function dropJourneyTables(?\PDO $pdo = null): void
    {
        $pdo ??= $this->pdo;

        foreach ([
            'kontor_crm_intake_responses',
            'kontor_crm_intake_profiles',
            'kontor_crm_deals',
            'kontor_crm_stages',
            'kontor_crm_pipelines',
            'kontor_crm_leads',
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
