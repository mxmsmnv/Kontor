<?php

declare(strict_types=1);

namespace Kontor\Tasks\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class TasksHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'tasks';
    }

    public function run(): HealthCheckResult
    {
        try {
            $open = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_tasks WHERE status IN ('open', 'in_progress') AND archived_at IS NULL")->fetchColumn();
            $dueReminders = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_task_reminders WHERE sent_at IS NULL AND remind_at <= UTC_TIMESTAMP(6)')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$open} open task(s), {$dueReminders} reminder(s) due.",
                ['openTasks' => $open, 'dueReminders' => $dueReminders],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Tasks tables are not reachable: {$e->getMessage()}");
        }
    }
}
