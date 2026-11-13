<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyService;
use Kontor\Automation\Domain\AutomationRule;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait AutomationDraftTools
{
    private function automationDraftTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorAutomation')) {
            return [];
        }
        return [$this->tool(
            'kontor_automation_rule_create_draft',
            'Create a paused Kontor automation rule',
            'Create a paused rule definition with a name and event identifier. Review the definition in Workspace before enabling it.',
            $this->mcpAutomationRuleCreateDraft(...),
            'draft',
            [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191],
                'trigger_event' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_.-]{2,190}$'],
                'idempotency_key' => $this->idempotencySchema(),
            ],
            ['name', 'trigger_event', 'idempotency_key'],
        )];
    }

    public function mcpAutomationRuleCreateDraft(string $name, string $trigger_event, string $idempotency_key): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-automation-rule-manage'))) {
            throw new WirePermissionException('The linked user cannot manage automation rules.');
        }

        $name = trim($this->wire()->sanitizer->text($name, ['maxLength' => 191]));
        if ($name === '' || !preg_match('/^[a-z][a-z0-9_.-]{2,190}$/D', $trigger_event)
            || !preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key)) {
            throw new WireException('Provide a rule name, an event identifier and a valid idempotency key.');
        }

        return $this->safe(function () use ($actor, $name, $trigger_event, $idempotency_key): array {
            $modules = $this->wire()->modules;
            $container = $modules->get('Kontor')->container();
            $organizations = $container->get(OrganizationRepository::class);
            $organization = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
            $database = $container->get(PDO::class);
            $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
            $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($database);
            try {
                $result = $idempotency->remember(
                    $organization,
                    'mcp:automation-draft:' . $actor->id . ':' . hash('sha256', $idempotency_key),
                    [$name, $trigger_event],
                    function () use ($modules, $container, $organizations, $organization, $actor, $name, $trigger_event): array {
                        $rule = AutomationRule::create($organization, $name, $trigger_event, (int) $actor->id);
                        $rule->status = 'paused';
                        $modules->get('KontorAutomation')->ruleRepository()->save($rule);
                        $uid = $rule->uid->toString();
                        $container->get(AuditLogger::class)->record(
                            $organizations->internalIdOf($organization), 'automation', 'rule', $uid,
                            'created', 'user', (string) $actor->id, metadata: ['transport' => 'mcp'],
                        );
                        return ['status' => 201, 'body' => ['uid' => $uid, 'status' => 'paused']];
                    },
                );
                if ($ownsTransaction) {
                    $database->commit();
                }
                return $result->body;
            } catch (\Throwable $error) {
                if ($ownsTransaction && $database->inTransaction()) {
                    $database->rollBack();
                }
                throw $error;
            }
        });
    }
}
