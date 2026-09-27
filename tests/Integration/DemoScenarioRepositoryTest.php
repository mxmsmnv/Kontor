<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Demo\Domain\DemoScenario;
use Kontor\Demo\Infrastructure\Persistence\DemoScenarioRepository;
use Kontor\Demo\Migrations\Migration0001CreateScenariosTable;

final class DemoScenarioRepositoryTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_demo_scenarios');
        }

        parent::tearDown();
    }

    public function test_scenario_progress_and_entity_map_round_trip(): void
    {
        (new MigrationRunner($this->pdo))->run([new Migration0001CreateScenariosTable()]);
        $organizations = new OrganizationRepository($this->pdo);
        $organization = $organizations->defaultOrganization('US', 'en', 'EUR');
        $repository = new DemoScenarioRepository($this->pdo, $organizations);
        $scenario = DemoScenario::create(
            $organization->uid->toString(),
            'Connected demo',
            41,
        );
        $scenario->record(['deal' => '01H00000000000000000000001']);
        $repository->save($scenario);

        $scenario->currentState = 'proposal';
        $scenario->record(['quotation' => '01H00000000000000000000002']);
        $repository->save($scenario);

        $restored = $repository->require($scenario->uid->toString());

        self::assertSame('proposal', $restored->currentState);
        self::assertSame('01H00000000000000000000001', $restored->entity('deal'));
        self::assertSame('01H00000000000000000000002', $restored->entity('quotation'));
        self::assertCount(1, $repository->forOrganization($organization->uid->toString()));
    }
}
