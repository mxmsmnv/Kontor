<?php

declare(strict_types=1);

namespace Kontor\Workflow\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;

final class WorkflowRepositoryListingTest extends DatabaseTestCase
{
    public function test_admin_listing_queries_exclude_archived_definitions_and_return_instances(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $definitions = new DefinitionRepository($this->pdo, $organizations);
        $transitions = new TransitionRepository($this->pdo, $organizations);
        $instances = new InstanceRepository($this->pdo, $organizations);
        $service = new WorkflowDefinitionService($definitions, $transitions);
        $engine = new WorkflowEngine(
            $definitions,
            $transitions,
            $instances,
            new ApprovalRequestRepository($this->pdo, $organizations),
            new HistoryRepository($this->pdo, $organizations),
        );
        $visible = $service->defineWorkflow(
            $this->organizationUid,
            'visible',
            'request',
            'Visible',
            'draft',
            ['draft', 'approved'],
        );
        $archived = $service->defineWorkflow(
            $this->organizationUid,
            'archived',
            'request',
            'Archived',
            'draft',
            ['draft', 'approved'],
        );
        $definitions->archive($archived->uid->toString());
        $instance = $engine->start(
            $this->organizationUid,
            $visible->uid->toString(),
            'request',
            'request-001',
        );

        $this->assertSame(['visible'], array_map(
            static fn ($definition): string => $definition->workflowKey,
            $definitions->forOrganization($this->organizationUid),
        ));
        $this->assertSame(
            $instance->uid->toString(),
            $instances->forDefinition($visible->uid->toString())[0]->uid->toString(),
        );
    }
}
