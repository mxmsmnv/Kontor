<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Events;

use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

/**
 * Synchronous in-process pub/sub implementing the SDK event contract
 * (kontor.md#9.3). Listeners run in descending priority order; a listener
 * throwing does not stop the remaining listeners for the same event.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, list<array{priority: int, listener: callable|string, seq: int}>>
     */
    private array $listeners = [];

    private int $sequence = 0;

    /** @var list<\Throwable> */
    private array $lastDispatchErrors = [];

    public function dispatch(KontorEvent $event): void
    {
        $this->lastDispatchErrors = [];

        $listeners = $this->listeners[$event->event] ?? [];

        usort(
            $listeners,
            static fn (array $a, array $b): int => $b['priority'] <=> $a['priority'] ?: $a['seq'] <=> $b['seq']
        );

        foreach ($listeners as $entry) {
            $listener = is_string($entry['listener']) ? new $entry['listener']() : $entry['listener'];

            try {
                $listener($event);
            } catch (\Throwable $e) {
                $this->lastDispatchErrors[] = $e;
            }
        }
    }

    public function subscribe(
        string $eventName,
        callable|string $listener,
        int $priority = 0
    ): void {
        $this->listeners[$eventName][] = [
            'priority' => $priority,
            'listener' => $listener,
            'seq' => $this->sequence++,
        ];
    }

    /**
     * @return list<\Throwable> errors thrown by listeners during the last dispatch() call
     */
    public function lastDispatchErrors(): array
    {
        return $this->lastDispatchErrors;
    }
}
