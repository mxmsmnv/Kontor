<?php

declare(strict_types=1);

namespace Kontor\Automation\Tests\Integration;

use Kontor\Automation\ActionHandlers\LogActionHandler;
use Kontor\Automation\Application\RuleDefinitionService;
use Kontor\Automation\Health\AutomationHealthCheck;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class AutomationHealthCheckTest extends DatabaseTestCase
{
    public function test_warning_when_no_action_handlers_are_registered(): void
    {
        $result = (new AutomationHealthCheck($this->pdo, new ActionHandlerRegistry()))->run();

        $this->assertSame('warning', $result->status);
    }

    public function test_ok_and_reports_active_rule_count(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $registry = new ActionHandlerRegistry();
        $registry->register(new LogActionHandler());

        $definitions = new RuleDefinitionService(
            new RuleRepository($this->pdo, $organizations), new ConditionRepository($this->pdo, $organizations),
            new ActionRepository($this->pdo, $organizations), $registry,
        );
        $definitions->defineRule($this->organizationUid, 'My rule', 'inventory.movement.completed');

        $result = (new AutomationHealthCheck($this->pdo, $registry))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeRules']);
        $this->assertSame(1, $result->details['registeredActionHandlers']);
    }
}
