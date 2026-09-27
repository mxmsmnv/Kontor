<?php

declare(strict_types=1);

namespace Kontor\Automation\Contracts;

/**
 * Not a canonical SDK contract (kontor.md section 9 has no automation
 * action entry) — this package's own extension point, same status as
 * kontor/dashboard's WidgetProviderInterface or kontor/search's own
 * SearchIndexerInterface. Lives local to this package rather than the
 * SDK for the same reason those do.
 */
interface ActionHandlerInterface
{
    public function key(): string;

    /**
     * @param array<string, mixed> $eventData the triggering KontorEvent's data
     * @param array<string, mixed> $params the rule action's own configured params
     * @return array<string, mixed> a result payload recorded in the execution log
     */
    public function execute(array $eventData, array $params): array;
}
