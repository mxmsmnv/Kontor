<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\Events\KontorEvent;

interface EventDispatcherInterface
{
    public function dispatch(KontorEvent $event): void;

    public function subscribe(
        string $eventName,
        callable|string $listener,
        int $priority = 0
    ): void;
}
