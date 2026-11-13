<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Automation\Domain\AutomationRule;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Workflow\Domain\WorkflowDefinition;
use ProcessWire\WirePermissionException;

trait WorkflowLookupTools
{
    private function workflowLookupTools(): array
    {
        $modules = $this->wire()->modules;
        $tools = [];
        if ($modules->isInstalled('KontorWorkflow')) {
            $tools[] = $this->tool(
                'kontor_workflows_list', 'List Kontor workflows',
                'List up to 50 workflow definitions in the active organization.',
                $this->mcpWorkflowsList(...),
            );
        }
        if ($modules->isInstalled('KontorAutomation')) {
            $tools[] = $this->tool(
                'kontor_automation_rules_list', 'List Kontor automation rules',
                'List up to 50 automation rule summaries in the active organization.',
                $this->mcpAutomationRulesList(...),
            );
        }
        return $tools;
    }

    public function mcpWorkflowsList(): array
    {
        $organization = $this->mcpWorkflowOrganization('kontor-workflow-definition-view');
        $module = $this->wire()->modules->get('KontorWorkflow');

        return ['items' => array_map(static fn (WorkflowDefinition $definition): array => [
            'uid' => $definition->uid->toString(),
            'key' => $definition->workflowKey,
            'entity_type' => $definition->entityType,
            'name' => $definition->name,
            'initial_state' => $definition->initialState,
            'status' => $definition->status,
        ], $module->definitionRepository()->forOrganization($organization, 50))];
    }

    public function mcpAutomationRulesList(): array
    {
        $organization = $this->mcpWorkflowOrganization('kontor-automation-rule-view');
        $module = $this->wire()->modules->get('KontorAutomation');

        return ['items' => array_map(static fn (AutomationRule $rule): array => [
            'uid' => $rule->uid->toString(),
            'name' => $rule->name,
            'trigger_event' => $rule->triggerEvent,
            'status' => $rule->status,
        ], $module->ruleRepository()->forOrganization($organization, 50))];
    }

    private function mcpWorkflowOrganization(string $permission): string
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission($permission))) {
            throw new WirePermissionException('The linked user lacks the required Workspace permission.');
        }

        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
    }
}
