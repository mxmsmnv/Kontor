<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\Tasks\Domain\Task;

/** The records a single user may access within the active organization. */
final class TaskAccessScope
{
    /** @param list<int> $departmentRoleIds */
    public function __construct(
        public readonly int $userId,
        public readonly bool $canView,
        public readonly bool $canViewAll = false,
        public readonly array $departmentRoleIds = [],
    ) {
    }

    public function allows(Task $task): bool
    {
        if (!$this->canView) {
            return false;
        }

        return $this->canViewAll || match ($task->visibility) {
            Task::VISIBILITY_ORGANIZATION => true,
            Task::VISIBILITY_ASSIGNEE => $task->assignedTo === $this->userId,
            Task::VISIBILITY_DEPARTMENT => in_array($task->departmentRoleId, $this->departmentRoleIds, true),
            default => false,
        };
    }
}
