<?php

declare(strict_types=1);

namespace Kontor\Tasks\Infrastructure\Dashboard;

use Kontor\Dashboard\Contracts\WidgetProviderInterface;
use Kontor\Tasks\Application\TaskAccessScope;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class MyOpenTasksWidgetProvider implements WidgetProviderInterface
{
    /** @param null|\Closure(int): TaskAccessScope $accessFor */
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ?\Closure $accessFor = null,
    ) {
    }

    public function key(): string
    {
        return 'tasks.my_open';
    }

    public function title(): string
    {
        return 'My open tasks';
    }

    public function render(string $organizationUid, ?int $userId): array
    {
        if ($userId === null) {
            return ['count' => 0, 'overdueCount' => 0, 'tasks' => []];
        }

        $access = $this->accessFor === null ? null : ($this->accessFor)($userId);
        $tasks = $this->tasks->openForAssignee($organizationUid, $userId, access: $access);

        return [
            'count' => count($tasks),
            'overdueCount' => count(array_filter($tasks, fn (Task $task): bool => $task->isOverdue())),
            'tasks' => array_map(
                static fn (Task $task): array => [
                    'uid' => $task->uid->toString(),
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'dueAt' => $task->dueAt?->format(DATE_ATOM),
                    'overdue' => $task->isOverdue(),
                ],
                $tasks,
            ),
        ];
    }
}
