<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use RuntimeException;

/**
 * The "tasks" milestone's own lifecycle, plus "recurrence": completing a
 * recurring task spawns its next occurrence rather than just closing it
 * out — the entire point of a recurring task is that completing one
 * instance produces the next.
 */
final class TaskWorkflowService
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function start(string $taskUid): Task
    {
        return $this->tasks->changeActiveTask($taskUid, function (Task $task) use ($taskUid): Task {
            if (!$task->isOpen()) {
                throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be started.");
            }

            $task->status = 'in_progress';
            $this->tasks->saveWorkflowState($task);

            return $task;
        });
    }

    /**
     * @return array{completed: Task, next: ?Task}
     */
    public function complete(string $taskUid): array
    {
        return $this->tasks->changeActiveTask($taskUid, function (Task $task) use ($taskUid): array {
            if (!$task->isOpen()) {
                throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be completed.");
            }

            $task->status = 'done';
            $task->completedAt = new \DateTimeImmutable();
            $this->tasks->saveWorkflowState($task);

            $advanced = $this->tasks->recurrenceAdvanced($taskUid) ?? false;
            $next = null;
            $nextDueAt = $advanced ? null : $task->nextOccurrenceDueAt($task->completedAt);
            if ($nextDueAt !== null) {
                $next = Task::create(
                    organizationId: $task->organizationId,
                    title: $task->title,
                    description: $task->description,
                    priority: $task->priority,
                    assignedTo: $task->assignedTo,
                    dueAt: $nextDueAt,
                    recurrenceRule: $task->recurrenceRule,
                    recurrenceUntil: $task->recurrenceUntil,
                );
                $this->tasks->save($next);
            }
            $this->tasks->recordRecurrenceAdvance($taskUid, $advanced || $next !== null);

            return ['completed' => $task, 'next' => $next];
        });
    }

    public function cancel(string $taskUid): Task
    {
        return $this->tasks->changeActiveTask($taskUid, function (Task $task) use ($taskUid): Task {
            if (!$task->isOpen()) {
                throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be cancelled.");
            }

            $task->status = 'cancelled';
            $this->tasks->saveWorkflowState($task);

            return $task;
        });
    }

    public function reopen(string $taskUid): Task
    {
        return $this->tasks->changeActiveTask($taskUid, function (Task $task) use ($taskUid): Task {
            if (!in_array($task->status, ['done', 'cancelled'], true)) {
                throw new RuntimeException("Task \"{$taskUid}\" is not closed and cannot be reopened.");
            }

            if ($task->status === 'done' && $this->tasks->recurrenceAdvanced($taskUid) === null) {
                // Earlier versions generated successors without recording the source occurrence.
                $this->tasks->recordRecurrenceAdvance(
                    $taskUid,
                    $task->nextOccurrenceDueAt($task->completedAt) !== null,
                );
            }
            $task->status = 'open';
            $task->completedAt = null;
            $this->tasks->saveWorkflowState($task);

            return $task;
        });
    }
}
