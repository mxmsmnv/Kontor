<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

/**
 * A fake `EventDispatcherInterface` that just records every dispatched
 * event, for asserting `OutboundMailService`/`InboundMailService` publish
 * the events they claim to.
 */
final class CapturingDispatcher implements EventDispatcherInterface
{
    /**
     * @var KontorEvent[]
     */
    public array $events = [];

    public function dispatch(KontorEvent $event): void
    {
        $this->events[] = $event;
    }

    public function subscribe(string $eventName, callable|string $listener, int $priority = 0): void
    {
    }
}
