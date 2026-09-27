<?php

declare(strict_types=1);

namespace Kontor\Automation\Health;

use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class AutomationHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ActionHandlerRegistry $actionHandlers,
    ) {
    }

    public function key(): string
    {
        return 'automation';
    }

    public function run(): HealthCheckResult
    {
        try {
            $activeRules = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_automation_rules WHERE status = 'active' AND archived_at IS NULL")->fetchColumn();
            $recursionBlocked = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_automation_execution_logs WHERE recursion_blocked = 1')->fetchColumn();
            $registeredActionHandlers = count($this->actionHandlers->all());

            if ($registeredActionHandlers === 0) {
                return new HealthCheckResult('warning', 'No action handlers are registered.', ['activeRules' => $activeRules, 'registeredActionHandlers' => 0]);
            }

            return new HealthCheckResult(
                'ok',
                "{$activeRules} active rule(s), {$registeredActionHandlers} registered action handler(s).",
                ['activeRules' => $activeRules, 'registeredActionHandlers' => $registeredActionHandlers, 'recursionBlockedCount' => $recursionBlocked],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Automation tables are not reachable: {$e->getMessage()}");
        }
    }
}
