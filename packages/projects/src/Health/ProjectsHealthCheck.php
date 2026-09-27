<?php

declare(strict_types=1);

namespace Kontor\Projects\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Beyond a plain count, checks a real invariant: no time entry should
 * have duration_minutes set while ended_at is still NULL (a running
 * timer) — TimeEntry::close() always sets both together.
 */
final class ProjectsHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'projects';
    }

    public function run(): HealthCheckResult
    {
        try {
            $activeProjects = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_projects WHERE status = 'active' AND archived_at IS NULL")->fetchColumn();
            $runningTimers = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_project_time_entries WHERE ended_at IS NULL')->fetchColumn();
            $inconsistent = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_project_time_entries WHERE ended_at IS NULL AND duration_minutes IS NOT NULL')->fetchColumn();

            if ($inconsistent > 0) {
                return new HealthCheckResult(
                    'critical',
                    "{$inconsistent} time entry(ies) have a duration but no end time.",
                    ['activeProjects' => $activeProjects, 'runningTimers' => $runningTimers, 'inconsistentEntries' => $inconsistent],
                );
            }

            return new HealthCheckResult(
                'ok',
                "{$activeProjects} active project(s), {$runningTimers} timer(s) running.",
                ['activeProjects' => $activeProjects, 'runningTimers' => $runningTimers, 'inconsistentEntries' => 0],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Projects tables are not reachable: {$e->getMessage()}");
        }
    }
}
