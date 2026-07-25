<?php

declare(strict_types=1);

namespace Kontor\Workflow\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Domain\ApprovalRequest;
use Kontor\Workflow\Domain\WorkflowDefinition;
use Kontor\Workflow\Domain\WorkflowInstance;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;

final class WorkflowEngineTest extends DatabaseTestCase
{
    private WorkflowDefinitionService $definitions;
    private WorkflowEngine $engine;
    private WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $definitionRepository = new DefinitionRepository($this->pdo, $organizations);
        $transitionRepository = new TransitionRepository($this->pdo, $organizations);

        $this->definitions = new WorkflowDefinitionService($definitionRepository, $transitionRepository);
        $this->engine = new WorkflowEngine(
            $definitionRepository, $transitionRepository, new InstanceRepository($this->pdo, $organizations),
            new ApprovalRequestRepository($this->pdo, $organizations), new HistoryRepository($this->pdo, $organizations),
        );

        $this->definition = $this->definitions->defineWorkflow(
            $this->organizationUid, 'simple_approval', 'expense', 'Simple approval', 'draft',
            ['draft', 'submitted', 'approved', 'rejected'],
        );
        $this->definitions->addTransition($this->definition->uid->toString(), 'submit', 'draft', 'submitted', requiredPermission: 'kontor-workflow-transition');
        $this->definitions->addTransition($this->definition->uid->toString(), 'approve', 'submitted', 'approved', requiresApproval: true);
        $this->definitions->addTransition($this->definition->uid->toString(), 'reject', 'submitted', 'rejected');
    }

    private function startedInstance(): WorkflowInstance
    {
        return $this->engine->start($this->organizationUid, $this->definition->uid->toString(), 'expense', 'exp_01');
    }

    public function test_full_lifecycle_through_the_state_machine(): void
    {
        $instance = $this->startedInstance();

        $this->assertSame('draft', $instance->currentState);
        $this->assertSame(['submit'], $this->engine->availableActions($this->organizationUid, 'expense', 'exp_01'));

        $submitted = $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit', actorUserId: 5);

        $this->assertInstanceOf(WorkflowInstance::class, $submitted);
        $this->assertSame('submitted', $submitted->currentState);
        $this->assertSame(['approve', 'reject'], $this->engine->availableActions($this->organizationUid, 'expense', 'exp_01'));
    }

    public function test_transition_is_blocked_without_the_required_permission(): void
    {
        $this->startedInstance();

        $this->expectException(\RuntimeException::class);
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit', actorHasPermission: false);
    }

    public function test_invalid_action_from_the_current_state_throws(): void
    {
        $this->startedInstance();

        $this->expectException(\RuntimeException::class);
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve');
    }

    public function test_cannot_start_a_second_instance_for_the_same_entity(): void
    {
        $this->startedInstance();

        $this->expectException(\RuntimeException::class);
        $this->engine->start($this->organizationUid, $this->definition->uid->toString(), 'expense', 'exp_01');
    }

    public function test_a_transition_requiring_approval_does_not_move_the_state_until_decided(): void
    {
        $this->startedInstance();
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');

        $result = $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve', actorUserId: 5);

        $this->assertInstanceOf(ApprovalRequest::class, $result);
        $this->assertTrue($result->isPending());
        $this->assertSame('submitted', $this->engine->currentState($this->organizationUid, 'expense', 'exp_01'));
    }

    public function test_approving_the_request_completes_the_transition(): void
    {
        $this->startedInstance();
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');
        $request = $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve');

        $instance = $this->engine->approve($request->uid->toString(), approvedBy: 9);

        $this->assertSame('approved', $instance->currentState);

        $history = $this->engine->history('expense', 'exp_01');
        $this->assertCount(2, $history);
        $this->assertSame('approve', $history[1]->actionKey);
        $this->assertSame(9, $history[1]->actorUserId);
    }

    public function test_rejecting_the_request_leaves_the_state_unchanged(): void
    {
        $this->startedInstance();
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');
        $request = $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve');

        $rejected = $this->engine->reject($request->uid->toString(), decidedBy: 9, reason: 'Not enough detail');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('submitted', $this->engine->currentState($this->organizationUid, 'expense', 'exp_01'));
    }

    public function test_cannot_decide_an_already_decided_request(): void
    {
        $this->startedInstance();
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');
        $request = $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve');
        $this->engine->approve($request->uid->toString(), approvedBy: 9);

        $this->expectException(\RuntimeException::class);
        $this->engine->approve($request->uid->toString(), approvedBy: 9);
    }

    public function test_a_terminal_state_has_no_available_actions(): void
    {
        $this->startedInstance();
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'reject');

        $this->assertSame([], $this->engine->availableActions($this->organizationUid, 'expense', 'exp_01'));

        $this->expectException(\RuntimeException::class);
        $this->engine->transition($this->organizationUid, 'expense', 'exp_01', 'reject');
    }
}
