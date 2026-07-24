<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Events;

use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\SDK\Events\KontorEvent;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function test_listeners_run_in_priority_order(): void
    {
        $dispatcher = new EventDispatcher();
        $order = [];

        $dispatcher->subscribe('invoice.paid', function () use (&$order) {
            $order[] = 'low';
        }, priority: 0);

        $dispatcher->subscribe('invoice.paid', function () use (&$order) {
            $order[] = 'high';
        }, priority: 10);

        $dispatcher->dispatch($this->event('invoice.paid'));

        $this->assertSame(['high', 'low'], $order);
    }

    public function test_a_listener_exception_does_not_stop_other_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $secondRan = false;

        $dispatcher->subscribe('invoice.paid', function () {
            throw new \RuntimeException('listener blew up');
        });
        $dispatcher->subscribe('invoice.paid', function () use (&$secondRan) {
            $secondRan = true;
        });

        $dispatcher->dispatch($this->event('invoice.paid'));

        $this->assertTrue($secondRan);
        $this->assertCount(1, $dispatcher->lastDispatchErrors());
    }

    public function test_only_matching_event_name_listeners_fire(): void
    {
        $dispatcher = new EventDispatcher();
        $fired = false;

        $dispatcher->subscribe('invoice.paid', function () use (&$fired) {
            $fired = true;
        });

        $dispatcher->dispatch($this->event('invoice.cancelled'));

        $this->assertFalse($fired);
    }

    private function event(string $name): KontorEvent
    {
        return KontorEvent::create(
            event: $name,
            organizationId: 'org_01',
            entityType: 'invoice',
            entityId: 'inv_01',
            actorType: 'user',
            actorId: 'usr_01',
        );
    }
}
