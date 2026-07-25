<?php

declare(strict_types=1);

namespace Kontor\Workflow\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Health\WorkflowHealthCheck;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;

final class WorkflowHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_pending_approval_count(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitionRepository = new DefinitionRepository($this->pdo, $organizations);
        $transitionRepository = new TransitionRepository($this->pdo, $organizations);
        $definitions = new WorkflowDefinitionService($definitionRepository, $transitionRepository);
        $engine = new WorkflowEngine(
            $definitionRepository, $transitionRepository, new InstanceRepository($this->pdo, $organizations),
            new ApprovalRequestRepository($this->pdo, $organizations), new HistoryRepository($this->pdo, $organizations),
        );

        $definition = $definitions->defineWorkflow($this->organizationUid, 'simple_approval', 'expense', 'Simple approval', 'draft', ['draft', 'submitted', 'approved']);
        $definitions->addTransition($definition->uid->toString(), 'submit', 'draft', 'submitted');
        $definitions->addTransition($definition->uid->toString(), 'approve', 'submitted', 'approved', requiresApproval: true);

        $engine->start($this->organizationUid, $definition->uid->toString(), 'expense', 'exp_01');
        $engine->transition($this->organizationUid, 'expense', 'exp_01', 'submit');
        $engine->transition($this->organizationUid, 'expense', 'exp_01', 'approve');

        $result = (new WorkflowHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeDefinitions']);
        $this->assertSame(1, $result->details['pendingApprovals']);
    }
}
