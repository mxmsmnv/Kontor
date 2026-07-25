<?php

declare(strict_types=1);

namespace Kontor\Workflow\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class WorkflowHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'workflow';
    }

    public function run(): HealthCheckResult
    {
        try {
            $activeDefinitions = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_workflow_definitions WHERE status = 'active' AND archived_at IS NULL")->fetchColumn();
            $pendingApprovals = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_workflow_approval_requests WHERE status = 'pending'")->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$activeDefinitions} active workflow definition(s), {$pendingApprovals} pending approval(s).",
                ['activeDefinitions' => $activeDefinitions, 'pendingApprovals' => $pendingApprovals],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Workflow tables are not reachable: {$e->getMessage()}");
        }
    }
}
