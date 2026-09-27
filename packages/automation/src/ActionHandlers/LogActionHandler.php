<?php

declare(strict_types=1);

namespace Kontor\Automation\ActionHandlers;

use Kontor\Automation\Contracts\ActionHandlerInterface;

/**
 * A trivial, dependency-free built-in action proving the registry/execute
 * pipeline end-to-end — same role kontor/dashboard's WelcomeWidgetProvider
 * plays for its own registry. Genuinely useful on its own too: a "just
 * record that this rule fired, with whatever it saw" debugging/audit
 * action, not only a pipeline demo. Real per-component actions (send an
 * email, create a task, post a message, …) are future work for those
 * packages to build once kontor/automation exists to depend on.
 */
final class LogActionHandler implements ActionHandlerInterface
{
    public function key(): string
    {
        return 'log';
    }

    public function execute(array $eventData, array $params): array
    {
        return [
            'loggedEventData' => $eventData,
            'loggedParams' => $params,
            'loggedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
