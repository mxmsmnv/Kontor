<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Support;

use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<KontorEvent> */
    public array $dispatched = [];

    public function dispatch(KontorEvent $event): void
    {
        $this->dispatched[] = $event;
    }

    public function subscribe(string $eventName, callable|string $listener, int $priority = 0): void
    {
    }

    /**
     * @return list<KontorEvent>
     */
    public function eventsNamed(string $eventName): array
    {
        return array_values(array_filter($this->dispatched, static fn (KontorEvent $e): bool => $e->event === $eventName));
    }
}
