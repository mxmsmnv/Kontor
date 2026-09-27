<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Demo\Application\DemoScenarioService;
use Kontor\Demo\Domain\DemoScenario;
use Kontor\Demo\Infrastructure\Persistence\DemoScenarioRepository;
use Kontor\Demo\Migrations\Migration0001CreateScenariosTable;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;
use Kontor\Workflow\Migrations\Migration0001CreateDefinitionsTable;
use Kontor\Workflow\Migrations\Migration0002CreateTransitionsTable;
use Kontor\Workflow\Migrations\Migration0003CreateInstancesTable;
use Kontor\Workflow\Migrations\Migration0004CreateApprovalRequestsTable;
use Kontor\Workflow\Migrations\Migration0005CreateHistoryTable;

final class DemoScenarioServiceTest extends DatabaseTestCase
{
    private string $organizationUid;
    private DemoScenarioRepository $scenarios;
    private DemoScenarioService $service;
    private WorkflowEngine $workflow;
    private ApprovalRequestRepository $approvals;

    protected function setUp(): void
    {
        parent::setUp();

        $runner = new MigrationRunner($this->pdo);
        $runner->run([
            new Migration0001CreateDefinitionsTable(),
            new Migration0002CreateTransitionsTable(),
            new Migration0003CreateInstancesTable(),
            new Migration0004CreateApprovalRequestsTable(),
            new Migration0005CreateHistoryTable(),
            new Migration0001CreateScenariosTable(),
        ]);

        $organizations = new OrganizationRepository($this->pdo);
        $this->organizationUid = $organizations
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
        $definitions = new DefinitionRepository($this->pdo, $organizations);
        $transitions = new TransitionRepository($this->pdo, $organizations);
        $this->approvals = new ApprovalRequestRepository($this->pdo, $organizations);
        $this->workflow = new WorkflowEngine(
            $definitions,
            $transitions,
            new InstanceRepository($this->pdo, $organizations),
            $this->approvals,
            new HistoryRepository($this->pdo, $organizations),
        );
        $this->scenarios = new DemoScenarioRepository($this->pdo, $organizations);
        $this->service = new DemoScenarioService(
            $this->pdo,
            $this->scenarios,
            $definitions,
            $transitions,
            new WorkflowDefinitionService($definitions, $transitions),
            $this->workflow,
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            foreach (
                [
                    'kontor_demo_scenarios',
                    'kontor_workflow_history',
                    'kontor_workflow_approval_requests',
                    'kontor_workflow_instances',
                    'kontor_workflow_transitions',
                    'kontor_workflow_definitions',
                ] as $table
            ) {
                $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
            }
        }

        parent::tearDown();
    }

    public function test_full_order_to_cash_lifecycle_persists_entities_and_history(): void
    {
        $scenario = $this->startScenario();

        self::assertSame('intake', $scenario->currentState);
        self::assertSame('contact_01', $scenario->entity('contact'));
        self::assertSame('prepare_proposal', $this->service->nextAction($scenario));

        $scenario = $this->advance($scenario, 'prepare_proposal', ['quotation' => 'quote_01']);
        self::assertSame('proposal', $scenario->currentState);
        self::assertSame('quote_01', $scenario->entity('quotation'));

        $scenario = $this->advance($scenario, 'request_approval');
        self::assertSame('proposal', $scenario->currentState);
        self::assertSame('awaiting_approval', $scenario->status);
        self::assertNotNull($scenario->pendingApprovalUid);

        $scenario = $this->service->approve(
            $scenario->uid->toString(),
            9,
            static fn (): array => ['approved_proposal' => 'quote_01'],
        );
        self::assertSame('approval', $scenario->currentState);
        self::assertSame('active', $scenario->status);
        self::assertNull($scenario->pendingApprovalUid);

        $scenario = $this->advance($scenario, 'start_delivery', ['project' => 'project_01']);
        $scenario = $this->advance($scenario, 'issue_invoice', ['invoice' => 'invoice_01']);
        $scenario = $this->advance($scenario, 'settle', ['payment' => 'payment_01']);

        self::assertSame('completed', $scenario->currentState);
        self::assertSame('completed', $scenario->status);
        self::assertTrue($scenario->isComplete());
        self::assertNull($this->service->nextAction($scenario));
        self::assertSame('payment_01', $scenario->entity('payment'));

        $persisted = $this->scenarios->require($scenario->uid->toString());
        self::assertEquals($scenario->entities, $persisted->entities);
        self::assertSame(
            ['prepare_proposal', 'request_approval', 'start_delivery', 'issue_invoice', 'settle'],
            array_map(
                static fn ($entry): string => $entry->actionKey,
                $this->workflow->history(DemoScenarioService::ENTITY_TYPE, $scenario->uid->toString()),
            ),
        );
    }

    public function test_rejected_approval_can_be_requested_again(): void
    {
        $scenario = $this->startScenario();
        $scenario = $this->advance($scenario, 'prepare_proposal');
        $scenario = $this->advance($scenario, 'request_approval');
        $firstApprovalUid = $scenario->pendingApprovalUid;

        $scenario = $this->service->reject($scenario->uid->toString(), 9, 'Needs clearer terms');

        self::assertSame('proposal', $scenario->currentState);
        self::assertSame('active', $scenario->status);
        self::assertNull($scenario->pendingApprovalUid);
        self::assertSame('Approval rejected: Needs clearer terms', $scenario->lastError);
        self::assertSame('rejected', $this->approvals->require((string) $firstApprovalUid)->status);

        $scenario = $this->advance($scenario, 'request_approval');

        self::assertSame('awaiting_approval', $scenario->status);
        self::assertNotSame($firstApprovalUid, $scenario->pendingApprovalUid);
        self::assertTrue($this->approvals->require((string) $scenario->pendingApprovalUid)->isPending());
    }

    public function test_denied_transition_does_not_invoke_effect_or_change_scenario(): void
    {
        $scenario = $this->startScenario();
        $effectWasCalled = false;

        try {
            $this->service->advance(
                $scenario->uid->toString(),
                'prepare_proposal',
                7,
                false,
                static function () use (&$effectWasCalled): array {
                    $effectWasCalled = true;

                    return ['quotation' => 'must_not_exist'];
                },
            );
            self::fail('A transition without the required permission must fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('requires permission', $exception->getMessage());
        }

        self::assertFalse($effectWasCalled);
        $persisted = $this->scenarios->require($scenario->uid->toString());
        self::assertSame('intake', $persisted->currentState);
        self::assertNull($persisted->entity('quotation'));
        self::assertSame([], $this->workflow->history(
            DemoScenarioService::ENTITY_TYPE,
            $scenario->uid->toString(),
        ));
    }

    public function test_effect_failure_rolls_back_workflow_and_scenario_changes(): void
    {
        $scenario = $this->startScenario();

        try {
            $this->service->advance(
                $scenario->uid->toString(),
                'prepare_proposal',
                7,
                true,
                static fn (): never => throw new \RuntimeException('Synthetic proposal failure'),
            );
            self::fail('The synthetic effect failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic proposal failure', $exception->getMessage());
        }

        $persisted = $this->scenarios->require($scenario->uid->toString());
        self::assertSame('intake', $persisted->currentState);
        self::assertSame('intake', $this->workflow->currentState(
            $this->organizationUid,
            DemoScenarioService::ENTITY_TYPE,
            $scenario->uid->toString(),
        ));
        self::assertSame([], $this->workflow->history(
            DemoScenarioService::ENTITY_TYPE,
            $scenario->uid->toString(),
        ));
    }

    public function test_start_failure_rolls_back_scenario_and_lazy_workflow_definition(): void
    {
        try {
            $this->service->start(
                $this->organizationUid,
                'Broken demo',
                7,
                static fn (): never => throw new \RuntimeException('Synthetic intake failure'),
            );
            self::fail('The synthetic intake failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic intake failure', $exception->getMessage());
        }

        self::assertSame([], $this->scenarios->forOrganization($this->organizationUid));
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM kontor_workflow_definitions'
        )->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM kontor_workflow_transitions'
        )->fetchColumn());
    }

    private function startScenario(): DemoScenario
    {
        return $this->service->start(
            $this->organizationUid,
            'Integration scenario',
            7,
            static fn (): array => ['contact' => 'contact_01'],
        );
    }

    /**
     * @param array<string, string> $entities
     */
    private function advance(DemoScenario $scenario, string $action, array $entities = []): DemoScenario
    {
        return $this->service->advance(
            $scenario->uid->toString(),
            $action,
            7,
            true,
            static fn (): array => $entities,
        );
    }
}
