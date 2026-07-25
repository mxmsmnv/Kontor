<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\CRM\Migrations\Migration0001CreateLeadsTable;
use Kontor\CRM\Migrations\Migration0002CreatePipelinesTable;
use Kontor\CRM\Migrations\Migration0003CreateStagesTable;
use Kontor\CRM\Migrations\Migration0004CreateDealsTable;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected \PDO $pdo;
    protected string $organizationUid;

    protected function setUp(): void
    {
        $dsn = getenv('KONTOR_TEST_DB_DSN');

        if ($dsn === false) {
            $this->markTestSkipped(
                'Set KONTOR_TEST_DB_DSN (and _USER/_PASS) to a MySQL/MariaDB '.
                'instance to run this test. See ../../docker-compose.test.yml.'
            );
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->dropTables();

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateLeadsTable(),
            new Migration0002CreatePipelinesTable(),
            new Migration0003CreateStagesTable(),
            new Migration0004CreateDealsTable(),
        ]);

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
    }

    /**
     * A default deal pipeline with open/won/lost stages — the fixture
     * most CRM tests need.
     *
     * @return array{pipeline: Pipeline, stages: array<int, Stage>}
     */
    protected function createDefaultPipeline(): array
    {
        $pipelines = new PipelineRepository($this->pdo, new OrganizationRepository($this->pdo));
        $stages = new StageRepository($this->pdo);

        $pipeline = Pipeline::create($this->organizationUid, 'Sales', isDefault: true);
        $pipelines->save($pipeline);

        $stageFixtures = [
            Stage::create($pipeline->uid->toString(), 'qualified', ['en' => 'Qualified'], probability: 20, sortOrder: 1, stateType: 'open'),
            Stage::create($pipeline->uid->toString(), 'negotiation', ['en' => 'Negotiation'], probability: 60, sortOrder: 2, stateType: 'open'),
            Stage::create($pipeline->uid->toString(), 'won', ['en' => 'Won'], probability: 100, sortOrder: 3, stateType: 'won'),
            Stage::create($pipeline->uid->toString(), 'lost', ['en' => 'Lost'], probability: 0, sortOrder: 4, stateType: 'lost'),
        ];

        foreach ($stageFixtures as $stage) {
            $stages->save($stage);
        }

        return ['pipeline' => $pipeline, 'stages' => $stageFixtures];
    }

    private function dropTables(): void
    {
        foreach (
            [
                'kontor_crm_deals',
                'kontor_crm_stages',
                'kontor_crm_pipelines',
                'kontor_crm_leads',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
